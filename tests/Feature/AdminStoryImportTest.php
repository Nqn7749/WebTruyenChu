<?php

declare(strict_types=1);

use App\Jobs\ImportStoryChapter;
use App\Models\Chapter;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryUrlImportService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function storyImportAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create([
        'role_id' => $role->id,
        'status' => true,
        'email_verified_at' => now(),
    ]);
}

test('admin can preview a story URL and its chapter links', function () {
    Cache::flush();
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Truyện thử"><meta name="author" content="Tác giả"><meta property="og:description" content="Giới thiệu"></head><body><a href="/chapter/1">Chương 1: Bắt đầu</a><a href="/chapter/2">Chương 2: Tiếp tục</a></body></html>'),
    ]);

    $this->actingAs(storyImportAdmin())
        ->followingRedirects()
        ->post(route('admin.story-import.preview'), ['url' => 'https://8.8.8.8/story'])
        ->assertOk()
        ->assertSee('Truyện thử')
        ->assertSee('Chương 1: Bắt đầu')
        ->assertSee('Tác giả');
});

test('admin queues selected chapters as unpublished drafts and prevents duplicate stories', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Cache::flush();

    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Truyện thử"></head><body><a href="/chapter/1">Chương 1</a></body></html>'),
    ]);

    $admin = storyImportAdmin();
    $preview = app(StoryUrlImportService::class)->preview('https://8.8.8.8/story');
    $preview['requested_by'] = $admin->id;
    $token = str_repeat('a', 48);
    Cache::put('story-url-import:'.$token, $preview, now()->addMinutes(30));

    $response = $this->actingAs($admin)
        ->post(route('admin.story-import.store'), [
            'token' => $token,
            'chapters' => [0],
            'permission_confirmed' => '1',
        ])->assertRedirect()
        ->assertSessionHas('success');

    expect($response->headers->get('Location'))->toContain('/admin/story-import/batches/');

    $story = Story::query()->where('source_hash', hash('sha256', 'https://8.8.8.8/story'))->firstOrFail();

    expect($story->status_publish)->toBeFalse()
        ->and($story->chapters()->count())->toBe(0);

    Bus::assertBatched(fn ($batch) => $batch->name === 'Nhập truyện: Truyện thử' && count($batch->jobs) === 1);
    Http::assertNotSent(fn ($request) => $request->url() === 'https://8.8.8.8/chapter/1');

    $this->post(route('admin.story-import.store'), [
        'token' => $token,
        'chapters' => [0],
        'permission_confirmed' => '1',
    ])->assertRedirect(route('admin.story-import.index'))
        ->assertSessionHasErrors('url');

    expect(Story::query()->count())->toBe(1);
});

test('queued chapter job imports content outside a transaction and is idempotent', function () {
    $story = Story::factory()->create();
    $transactionLevel = DB::transactionLevel();
    $url = 'https://8.8.8.8/chapter/1';
    $hash = hash('sha256', $url);

    Http::fake([
        $url => function () use ($transactionLevel) {
            expect(DB::transactionLevel())->toBe($transactionLevel);

            return Http::response('<html><body><article><p>'.str_repeat('Nội dung chương thử nghiệm. ', 8).'</p></article></body></html>');
        },
    ]);

    $job = new ImportStoryChapter($story->id, 'Chương 1', $url, $hash, 1);
    $job->handle(app(StoryUrlImportService::class));
    $job->handle(app(StoryUrlImportService::class));

    expect(Chapter::query()->where('source_hash', $hash)->count())->toBe(1)
        ->and(Chapter::query()->where('source_hash', $hash)->first()->status)->toBeFalse();
});

test('queued chapter job does not save a chapter when the source request fails', function () {
    $story = Story::factory()->create();
    $url = 'https://8.8.8.8/chapter/failed';
    $hash = hash('sha256', $url);

    Http::fake([
        $url => Http::response('', 503),
    ]);

    $job = new ImportStoryChapter($story->id, 'Chương lỗi', $url, $hash, 1);

    expect(fn () => $job->handle(app(StoryUrlImportService::class)))
        ->toThrow(RuntimeException::class, 'HTTP 503');

    expect(Chapter::query()->where('source_hash', $hash)->exists())->toBeFalse();
});

test('story URL import rejects insecure URLs', function () {
    $this->actingAs(storyImportAdmin())
        ->post(route('admin.story-import.preview'), ['url' => 'http://8.8.8.8/story'])
        ->assertSessionHasErrors('url');
});

test('admin can view queued chapter batch progress', function () {
    Queue::fake();

    $admin = storyImportAdmin();
    $story = Story::factory()->create();
    $url = 'https://8.8.8.8/chapter/progress';
    $batch = Bus::batch([
        new ImportStoryChapter($story->id, 'Chương chờ', $url, hash('sha256', $url), 1),
    ])->name('Nhập truyện: Theo dõi tiến trình')->allowFailures()->dispatch();

    $this->actingAs($admin)
        ->get(route('admin.story-import.batch', $batch->id))
        ->assertOk()
        ->assertSee('Tiến trình nhập truyện')
        ->assertSee('Nhập truyện: Theo dõi tiến trình')
        ->assertSee('Tổng số chương')
        ->assertSee('Đang chờ');
});

test('batch progress page counts failures correctly and stops refreshing once all jobs ran', function () {
    Queue::fake();

    $story = Story::factory()->create();
    $jobs = collect(range(1, 3))->map(function (int $n) use ($story): ImportStoryChapter {
        $url = 'https://8.8.8.8/chapter/'.$n;

        return new ImportStoryChapter($story->id, 'Chương '.$n, $url, hash('sha256', $url), $n);
    })->all();

    $batch = Bus::batch($jobs)->name('Nhập truyện: Trộn kết quả')->allowFailures()->dispatch();
    $admin = storyImportAdmin();

    // Still running: 1 success, 2 waiting.
    $batch->recordSuccessfulJob('job-ok');
    $this->actingAs($admin)
        ->get(route('admin.story-import.batch', $batch->id))
        ->assertOk()
        ->assertSee('Đang xử lý')
        ->assertSee('http-equiv="refresh"', false);

    // Finish the rest: one succeeds, one fails.
    $failedUuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $failedUuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => "RuntimeException: Chương 3 (https://8.8.8.8/chapter/3): Không thể đọc website nguồn (HTTP 503). in /var/www/app/Services/StoryUrlImportService.php:107\nStack trace:\n#0 x",
        'failed_at' => now(),
    ]);
    $batch = Bus::findBatch($batch->id);
    $batch->recordSuccessfulJob('job-ok-2');
    $batch->recordFailedJob($failedUuid, new RuntimeException('boom'));

    $response = $this->actingAs($admin)
        ->get(route('admin.story-import.batch', $batch->id))
        ->assertOk()
        ->assertSee('Hoàn tất có chương lỗi')
        ->assertDontSee('http-equiv="refresh"', false)
        ->assertSee('Chương 3 (https://8.8.8.8/chapter/3): Không thể đọc website nguồn (HTTP 503).')
        ->assertDontSee('/var/www/app')
        ->assertSee('queue:retry-batch');

    $fresh = Bus::findBatch($batch->id);
    expect($fresh->processedJobs())->toBe(2)
        ->and($fresh->failedJobs)->toBe(1)
        ->and($fresh->pendingJobs - $fresh->failedJobs)->toBe(0);
});

test('batch progress page rejects non-uuid batch ids', function () {
    $this->actingAs(storyImportAdmin())
        ->get('/admin/story-import/batches/not-a-uuid')
        ->assertNotFound();
});

test('imported chapters keep the chapter number parsed from the source title', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();

    $admin = storyImportAdmin();
    $token = str_repeat('b', 48);
    Cache::put('story-url-import:'.$token, [
        'source_url' => 'https://8.8.8.8/numbered',
        'host' => '8.8.8.8',
        'title' => 'Truyện đánh số',
        'author' => '',
        'description' => '',
        'requested_by' => $admin->id,
        'chapters' => [
            ['title' => 'Chương 5', 'url' => 'https://8.8.8.8/c/5', 'number' => 5],
            ['title' => 'Chương 6', 'url' => 'https://8.8.8.8/c/6', 'number' => 6],
        ],
    ], now()->addMinutes(30));

    $this->actingAs($admin)->post(route('admin.story-import.store'), [
        'token' => $token,
        'chapters' => [0, 1],
        'permission_confirmed' => '1',
    ])->assertRedirect();

    Bus::assertBatched(fn ($batch) => $batch->jobs->map(fn ($job) => $job->chapterNumber)->all() === [5, 6]);
});

test('story URL import blocks internal, reserved and malformed targets before any request', function (string $url) {
    Http::fake();

    expect(fn () => app(StoryUrlImportService::class)->preview($url))->toThrow(RuntimeException::class);

    Http::assertNothingSent();
})->with([
    'loopback' => 'https://127.0.0.1/story',
    'private 10/8' => 'https://10.0.0.5/story',
    'private 192.168' => 'https://192.168.1.10/story',
    'cloud metadata' => 'https://169.254.169.254/latest/meta-data',
    'carrier-grade NAT' => 'https://100.64.0.1/story',
    'ipv6 loopback' => 'https://[::1]/story',
    'ipv4-mapped ipv6' => 'https://[::ffff:127.0.0.1]/story',
    'ipv6 unique local' => 'https://[fd00::1]/story',
    'ipv4-compatible ipv6' => 'https://[::7f00:1]/story',
    'teredo' => 'https://[2001:0:4136:e378:8000:63bf:3fff:fdd2]/story',
    'ipv6 link local' => 'https://[fe80::1]/story',
    'localhost' => 'https://localhost/story',
    'internal suffix' => 'https://db.internal/story',
    'custom port' => 'https://8.8.8.8:8443/story',
    'credentials' => 'https://user:pass@8.8.8.8/story',
    'plain http' => 'http://8.8.8.8/story',
]);

test('story URL import rejects a hostname when any resolved address is internal', function (array $addresses) {
    Http::fake();

    $importer = new class($addresses) extends StoryUrlImportService
    {
        public function __construct(private array $addresses) {}

        protected function resolveHost(string $host): array
        {
            return $this->addresses;
        }
    };

    expect(fn () => $importer->preview('https://source.example/story'))
        ->toThrow(RuntimeException::class, 'không được phép');

    Http::assertNothingSent();
})->with([
    'only private' => [['10.0.0.5']],
    'mixed public and private' => [['8.8.8.8', '10.0.0.5']],
    'public ipv4 and loopback ipv6' => [['8.8.8.8', '::1']],
]);

test('story URL import reports an unresolvable hostname', function () {
    Http::fake();

    $importer = new class extends StoryUrlImportService
    {
        protected function resolveHost(string $host): array
        {
            return [];
        }
    };

    expect(fn () => $importer->preview('https://missing.example/story'))
        ->toThrow(RuntimeException::class, 'Không phân giải');
});

test('story URL import rejects non-HTML responses', function () {
    Http::fake([
        'https://8.8.8.8/story' => Http::response('{"a":1}', 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn () => app(StoryUrlImportService::class)->preview('https://8.8.8.8/story'))
        ->toThrow(RuntimeException::class, 'không trả về trang HTML');
});

test('story URL import rejects oversized pages', function () {
    Http::fake([
        'https://8.8.8.8/story' => Http::response(str_repeat('a', 2_000_001), 200, ['Content-Type' => 'text/html']),
    ]);

    expect(fn () => app(StoryUrlImportService::class)->preview('https://8.8.8.8/story'))
        ->toThrow(RuntimeException::class, '2 MB');
});

test('story URL import with allowlist enabled rejects hosts that are not listed', function () {
    Http::fake();
    config(['story_import.require_allowlist' => true, 'story_import.sources' => ['allowed.example' => []]]);

    expect(fn () => app(StoryUrlImportService::class)->preview('https://8.8.8.8/story'))
        ->toThrow(RuntimeException::class, 'chưa được cấp phép');

    Http::assertNothingSent();
});

test('story URL import with allowlist enabled accepts listed hosts', function () {
    config(['story_import.require_allowlist' => true, 'story_import.sources' => ['8.8.8.8' => []]]);
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Truyen A"></head><body><a href="/chapter/1">Chương 1: Bắt đầu</a></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    expect(app(StoryUrlImportService::class)->preview('https://8.8.8.8/story'))
        ->toHaveKey('title', 'Truyen A');
});

test('story URL import uses the host profile XPath for metadata and chapter links', function () {
    config(['story_import.sources' => ['8.8.8.8' => [
        'title' => '//h2[@class="name"]',
        'author' => '//*[@class="by"]',
        'description' => '//*[@id="intro"]',
        'chapter_links' => '//ul[@id="toc"]//a[@href]',
        'remove' => ['//div[@class="ads"]'],
    ]]]);
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Generic"></head><body>'
            .'<h2 class="name">Truyện riêng</h2><span class="by">Tác giả X</span><p id="intro">Giới thiệu riêng</p>'
            .'<div class="ads"><ul id="toc"><li><a href="/ads/1">Quảng cáo</a></li></ul></div>'
            .'<ul id="toc"><li><a href="/c/1">Mở đầu</a></li><li><a href="/c/2">Phần hai</a></li></ul>'
            .'<a href="/chapter/9">Chương 9: không thuộc danh sách</a></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = app(StoryUrlImportService::class)->preview('https://8.8.8.8/story');

    expect($result['title'])->toBe('Truyện riêng')
        ->and($result['author'])->toBe('Tác giả X')
        ->and($result['description'])->toBe('Giới thiệu riêng')
        ->and(array_column($result['chapters'], 'title'))->toBe(['Mở đầu', 'Phần hai']);
});

test('story URL import uses the host profile XPath for chapter content', function () {
    config(['story_import.sources' => ['8.8.8.8' => ['chapter_content' => '//div[@id="body"]', 'remove' => ['//div[@class="ads"]']]]]);
    $text = str_repeat('Nội dung chương rất dài. ', 10);
    Http::fake([
        'https://8.8.8.8/c/1' => Http::response('<html><body><article><p>Bài viết khác không liên quan</p></article>'
            .'<div id="body"><div class="ads"><p>QUẢNG CÁO</p></div><p>'.$text.'</p></div></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $html = app(StoryUrlImportService::class)->fetchChapter('https://8.8.8.8/c/1');

    expect($html)->toContain('Nội dung chương rất dài')->not->toContain('QUẢNG CÁO')->not->toContain('không liên quan');
});

test('story URL import falls back to the generic parser for hosts without a profile', function () {
    config(['story_import.sources' => ['other.example' => ['title' => '//h2']]]);
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Generic"></head><body><a href="/chapter/1">Chương 1: A</a></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    expect(app(StoryUrlImportService::class)->preview('https://8.8.8.8/story')['title'])->toBe('Generic');
});

test('story URL import ignores an invalid profile XPath instead of failing', function () {
    config(['story_import.sources' => ['8.8.8.8' => ['title' => '//[broken', 'remove' => ['//[broken']]]]);
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Generic"></head><body><a href="/chapter/1">Chương 1: A</a></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    expect(app(StoryUrlImportService::class)->preview('https://8.8.8.8/story')['title'])->toBe('Generic');
});
