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
