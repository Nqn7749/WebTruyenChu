<?php

use App\Jobs\SendBulkChapterNotifications;
use App\Jobs\SendNewChapterNotifications;
use App\Models\Chapter;
use App\Models\Favorite;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('publishing a chapter on a public story dispatches one notification batch job', function () {
    Queue::fake();

    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create([
        'user_id' => $author->id,
        'status_publish' => true,
    ]);

    $this->actingAs($author)
        ->post(route('writer.stories.chapters.store', $story), [
            'chapter_number' => 1,
            'title' => 'Chương đầu',
            'content' => 'Nội dung chương đầu.',
            'status' => true,
        ])
        ->assertRedirect(route('writer.stories.chapters.index', $story));

    $chapter = Chapter::query()->where('story_id', $story->id)->firstOrFail();

    Queue::assertPushed(SendNewChapterNotifications::class, fn ($job) => $job->chapterId === $chapter->id);
});

test('notification job sends one queued batch only to followers who opted in', function () {
    Notification::fake();

    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $subscribed = User::factory()->create();
    $unsubscribed = User::factory()->create();

    Favorite::create([
        'user_id' => $subscribed->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);
    Favorite::create([
        'user_id' => $unsubscribed->id,
        'story_id' => $story->id,
        'notify_new_chapter' => false,
    ]);

    (new SendNewChapterNotifications($chapter->id))->handle();

    Notification::assertSentTo($subscribed, NewChapterPublished::class);
    Notification::assertNotSentTo($unsubscribed, NewChapterPublished::class);
});

test('notification job skips chapters belonging to unpublished stories', function () {
    Notification::fake();

    $story = Story::factory()->create(['status_publish' => false]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $follower = User::factory()->create();

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    (new SendNewChapterNotifications($chapter->id))->handle();

    Notification::assertNotSentTo($follower, NewChapterPublished::class);
});

test('republishing a story notifies followers about already published chapters', function () {
    Queue::fake();

    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => false]);
    $publishedChapter = Chapter::factory()->for($story)->create(['status' => true]);
    $draftChapter = Chapter::factory()->for($story)->create(['status' => false]);

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-publish', $story))
        ->assertRedirect();

    expect($story->fresh()->status_publish)->toBeTrue();
    Queue::assertPushed(SendBulkChapterNotifications::class, fn ($job) => $job->chapterIds === [$publishedChapter->id]);
    Queue::assertNotPushed(SendNewChapterNotifications::class);
});

test('notification job does not resend to users who already received it', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $follower = User::factory()->create();

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    // Giả lập job đã gửi một lần rồi fail giữa batch: trong DB đã có sẵn
    // thông báo cho chương này, lần chạy lại phải bỏ qua người này.
    $follower->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => NewChapterPublished::class,
        'data' => [
            'story_id' => $story->id,
            'story_title' => $story->title,
            'story_slug' => $story->slug,
            'chapter_id' => $chapter->id,
            'chapter_number' => $chapter->chapter_number,
            'chapter_title' => $chapter->title,
            'message' => 'test',
            'url' => '/',
        ],
    ]);

    Notification::fake();

    (new SendNewChapterNotifications($chapter->id))->handle();

    Notification::assertNotSentTo($follower, NewChapterPublished::class);
});
