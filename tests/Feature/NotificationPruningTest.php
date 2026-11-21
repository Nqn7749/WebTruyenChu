<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use App\Services\StoryDeletionService;
use Illuminate\Support\Str;

test('a user can delete their own notification', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => NewChapterPublished::class,
        'data' => ['message' => 'test', 'url' => '/'],
    ]);

    $this->actingAs($user)
        ->delete(route('notifications.destroy', $notification->id))
        ->assertRedirect();

    expect($user->notifications()->count())->toBe(0);
});

test('a user cannot delete another users notification', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $notification = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => NewChapterPublished::class,
        'data' => ['message' => 'test', 'url' => '/'],
    ]);

    $this->actingAs($other)
        ->delete(route('notifications.destroy', $notification->id))
        ->assertNotFound();

    expect($owner->notifications()->count())->toBe(1);
});

test('force-deleting a chapter removes its notifications', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $follower = User::factory()->create();
    $follower->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => NewChapterPublished::class,
        'data' => [
            'story_id' => $story->id,
            'chapter_id' => $chapter->id,
            'message' => 'test',
            'url' => '/',
        ],
    ]);

    $chapter->forceDelete();

    expect($follower->notifications()->count())->toBe(0);
});

test('force-deleting a story removes its notifications', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $follower = User::factory()->create();
    $follower->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => NewChapterPublished::class,
        'data' => [
            'story_id' => $story->id,
            'chapter_id' => $chapter->id,
            'message' => 'test',
            'url' => '/',
        ],
    ]);

    expect(app(StoryDeletionService::class)->forceDelete($story, 'test'))->toBeTrue();

    expect($follower->notifications()->count())->toBe(0);
});
