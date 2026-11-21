<?php

use App\Jobs\SendBulkChapterNotifications;
use App\Jobs\SendNewChapterNotifications;
use App\Models\Chapter;
use App\Models\Favorite;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('notification job re-checks chapter status after waiting for the lock', function () {
    Notification::fake();

    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $follower = User::factory()->create();

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    // Giữ lock 2 giây, gỡ công khai chương trong lúc job đang chờ lock.
    $lock = Cache::lock('notify-chapter-'.$chapter->id, 2);
    $lock->acquire();
    $chapter->forceFill(['status' => false])->save();

    // Job chờ lock rồi đọc lại trạng thái mới (đã gỡ công khai) và bỏ qua.
    expect(SendNewChapterNotifications::notifyChapter($chapter->id))->toBeTrue();
    Notification::assertNotSentTo($follower, NewChapterPublished::class);
});

test('notification job reports a busy lock instead of failing', function () {
    Notification::fake();

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
        expect(SendNewChapterNotifications::notifyChapter($chapter->id, 1))->toBeFalse();
        Notification::assertNotSentTo($follower, NewChapterPublished::class);
    } finally {
        $lock->release();
    }
});

test('bulk notification job skips a locked chapter and re-queues it', function () {
    Notification::fake();
    Queue::fake();

    $story = Story::factory()->create(['status_publish' => true]);
    $chapterA = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 1]);
    $chapterB = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 2]);
    $chapterC = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 3]);
    $follower = User::factory()->create();

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    $lock = Cache::lock('notify-chapter-'.$chapterB->id, 120);
    $lock->acquire();

    try {
        (new SendBulkChapterNotifications([$chapterA->id, $chapterB->id, $chapterC->id], 1))->handle();

        // A và C vẫn gửi; B chưa gửi.
        Notification::assertSentTo($follower, NewChapterPublished::class, fn ($notification) => $notification->chapter->id === $chapterA->id);
        Notification::assertSentTo($follower, NewChapterPublished::class, fn ($notification) => $notification->chapter->id === $chapterC->id);
        Notification::assertNotSentTo($follower, NewChapterPublished::class, fn ($notification) => $notification->chapter->id === $chapterB->id);

        // B được dispatch lại để gửi bù sau.
        Queue::assertPushed(SendBulkChapterNotifications::class, fn ($job) => $job->chapterIds === [$chapterB->id]);
    } finally {
        $lock->release();
    }
});
