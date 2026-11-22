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
