<?php

use App\Jobs\SendBulkChapterNotifications;
use App\Jobs\SendNewChapterNotifications;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Favorite;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

test('single notification job dispatches a fresh job when the lock is busy', function () {
    Notification::fake();
    Queue::fake();

    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $follower = User::factory()->create();

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    $lock = Cache::lock('notify-chapter-'.$chapter->id, 120);
    $lock->acquire();

    try {
        // waitSeconds = 1 để test không chờ 60 giây thật.
        (new SendNewChapterNotifications($chapter->id, 1))->handle();

        Notification::assertNotSentTo($follower, NewChapterPublished::class);

        // Job mới được dispatch (có tries mới) thay vì release() tốn
        // một lượt tries rồi cạn và mất thông báo (C1).
        Queue::assertPushed(SendNewChapterNotifications::class, fn ($job) => $job->chapterId === $chapter->id);
    } finally {
        $lock->release();
    }
});

test('bulk notification job stops waiting once its lock budget is spent', function () {
    Notification::fake();
    Queue::fake();

    $story = Story::factory()->create(['status_publish' => true]);
    $chapterA = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 1]);
    $chapterB = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 2]);
    $follower = User::factory()->create();

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    $lockA = Cache::lock('notify-chapter-'.$chapterA->id, 120);
    $lockB = Cache::lock('notify-chapter-'.$chapterB->id, 120);
    $lockA->acquire();
    $lockB->acquire();

    try {
        $startedAt = microtime(true);

        // Ngân sách chờ lock chỉ 2 giây, waitSeconds 60 giây: nếu không
        // có deadline thì job chờ 60s/chương (tổng 120s); có deadline
        // thì chương đầu chờ ~2s, chương sau chờ 0s (C3).
        (new SendBulkChapterNotifications([$chapterA->id, $chapterB->id], 60, 2))->handle();

        expect(microtime(true) - $startedAt)->toBeLessThan(30);

        Notification::assertNotSentTo($follower, NewChapterPublished::class);

        // Cả hai chương kẹt đều được dispatch lại để gửi bù.
        Queue::assertPushed(SendBulkChapterNotifications::class, fn ($job) => $job->chapterIds === [$chapterA->id, $chapterB->id]);
    } finally {
        $lockA->release();
        $lockB->release();
    }
});

test('deleting an account removes its notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $story = Story::factory()->create();

    $ownId = (string) Str::uuid();
    $otherId = (string) Str::uuid();

    DB::table('notifications')->insert([
        [
            'id' => $ownId,
            'type' => NewChapterPublished::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['story_id' => $story->id, 'chapter_id' => 1]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $otherId,
            'type' => NewChapterPublished::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $other->id,
            'data' => json_encode(['story_id' => $story->id, 'chapter_id' => 1]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'Password1234'])
        ->assertRedirect('/');

    // Notification của tài khoản đã xóa bị dọn, của người khác còn nguyên (C4).
    expect(DB::table('notifications')->where('id', $ownId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $otherId)->exists())->toBeTrue();
});

test('scheduled prune deletes unread notifications older than one year', function () {
    $user = User::factory()->create();

    $oldUnreadId = (string) Str::uuid();
    $recentUnreadId = (string) Str::uuid();
    $oldReadId = (string) Str::uuid();

    DB::table('notifications')->insert([
        [
            'id' => $oldUnreadId,
            'type' => NewChapterPublished::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['story_id' => 1, 'chapter_id' => 1]),
            'read_at' => null,
            'created_at' => now()->subDays(400),
            'updated_at' => now()->subDays(400),
        ],
        [
            'id' => $recentUnreadId,
            'type' => NewChapterPublished::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['story_id' => 1, 'chapter_id' => 1]),
            'read_at' => null,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ],
        [
            'id' => $oldReadId,
            'type' => NewChapterPublished::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['story_id' => 1, 'chapter_id' => 1]),
            'read_at' => now()->subDays(100),
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ],
    ]);

    // Chạy đúng lịch 03:00 để task prune tới hạn (C5).
    $this->travelTo(now()->setTime(3, 0));
    Artisan::call('schedule:run');

    expect(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentUnreadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse();
});

test('very long titles produce slugs within 255 characters', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $title = str_repeat('a', 254);

    $payload = [
        'title' => $title,
        'description' => 'Mô tả truyện.',
        'status' => 'ongoing',
        'categories' => [$category->id],
        'tags' => [],
    ];

    $this->actingAs($user)
        ->post(route('writer.stories.store'), $payload)
        ->assertRedirect(route('writer.stories.index'));

    $first = Story::query()->where('title', $title)->firstOrFail();
    expect(mb_strlen($first->slug))->toBeLessThanOrEqual(255);

    // Đăng lần hai cùng tiêu đề: slug trùng phải thêm hậu tố mà vẫn
    // không vượt 255 ký tự (V3).
    $this->actingAs($user)
        ->post(route('writer.stories.store'), $payload)
        ->assertRedirect(route('writer.stories.index'));

    $stories = Story::query()->where('title', $title)->orderBy('id')->get();

    expect($stories)->toHaveCount(2)
        ->and(mb_strlen($stories[1]->slug))->toBeLessThanOrEqual(255)
        ->and($stories[1]->slug)->not->toBe($stories[0]->slug);
});

test('story search scope has been removed', function () {
    expect(method_exists(Story::class, 'scopeSearch'))->toBeFalse();
});

test('toggling publish twice returns the story to its original state', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $story = Story::factory()->create(['status_publish' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-publish', $story))
        ->assertRedirect();

    expect($story->fresh()->status_publish)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-publish', $story))
        ->assertRedirect();

    expect($story->fresh()->status_publish)->toBeTrue();
});

test('admin routes are throttled', function () {
    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.'));

    expect($adminRoutes)->not->toBeEmpty();

    foreach ($adminRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('throttle:60,1');
    }
});
