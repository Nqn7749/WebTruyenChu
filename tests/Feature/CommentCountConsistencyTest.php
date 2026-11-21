<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryStatsService;

test('refresh recount includes replies of a visible parent', function () {
    $story = Story::factory()->create();
    $parent = Comment::factory()->for($story)->create();
    Comment::factory()->for($story)->create(['parent_id' => $parent->id]);
    Comment::factory()->for($story)->create(['parent_id' => $parent->id]);

    // Giả lập số đếm đã lệch về 0.
    $story->forceFill(['comment_count' => 0])->saveQuietly();

    app(StoryStatsService::class)->refreshStories([$story->id]);

    expect($story->fresh()->comment_count)->toBe(3);
});

test('refresh recount excludes replies of a hidden parent', function () {
    $story = Story::factory()->create();
    $parent = Comment::factory()->for($story)->create();
    Comment::factory()->for($story)->create(['parent_id' => $parent->id]);
    Comment::factory()->for($story)->create(['parent_id' => $parent->id]);

    // Giả lập số đếm đã lệch: 3 = đếm cả reply của cha đã ẩn.
    $story->forceFill(['comment_count' => 3])->saveQuietly();
    $parent->forceFill(['is_hidden' => true])->save();

    app(StoryStatsService::class)->refreshStories([$story->id]);

    expect($story->fresh()->comment_count)->toBe(0);
});

test('refresh recount excludes comments of a soft-deleted chapter', function () {
    $story = Story::factory()->create();
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);

    $story->forceFill(['comment_count' => 2])->saveQuietly();
    $chapter->delete();

    app(StoryStatsService::class)->refreshStories([$story->id]);

    expect($story->fresh()->comment_count)->toBe(0);
});

test('soft-deleting a chapter subtracts its visible comments from the story count', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);

    $story->forceFill(['comment_count' => 2])->saveQuietly();
    $chapter->forceFill(['comment_count' => 2])->saveQuietly();

    $this->actingAs($author)
        ->delete(route('writer.chapters.destroy', $chapter))
        ->assertRedirect(route('writer.stories.chapters.index', $story));

    expect($story->fresh()->comment_count)->toBe(0);
    expect(Chapter::withTrashed()->find($chapter->id)->trashed())->toBeTrue();
});

test('toggling a reply while its parent is hidden does not change the counter', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);

    $story = Story::factory()->create();
    $parent = Comment::factory()->for($story)->create();
    $reply = Comment::factory()->for($story)->create(['parent_id' => $parent->id]);
    $story->forceFill(['comment_count' => 2])->saveQuietly();

    // Ẩn cha: số đếm 2 -> 0 (cả cha lẫn reply đều không còn hiển thị).
    $this->actingAs($admin)
        ->patch(route('admin.comments.toggle-hidden', $parent))
        ->assertRedirect();
    expect($story->fresh()->comment_count)->toBe(0);

    // Ẩn/hiện reply khi cha đang ẩn: số đếm không đổi.
    $this->actingAs($admin)
        ->patch(route('admin.comments.toggle-hidden', $reply))
        ->assertRedirect();
    expect($story->fresh()->comment_count)->toBe(0);

    $this->actingAs($admin)
        ->patch(route('admin.comments.toggle-hidden', $reply))
        ->assertRedirect();
    expect($story->fresh()->comment_count)->toBe(0);

    // Hiện lại cha: số đếm về 2.
    $this->actingAs($admin)
        ->patch(route('admin.comments.toggle-hidden', $parent))
        ->assertRedirect();
    expect($story->fresh()->comment_count)->toBe(2);
});

test('deleting a reply of a hidden parent does not change the counter', function () {
    $story = Story::factory()->create();
    $hiddenParent = Comment::factory()->for($story)->create(['is_hidden' => true]);
    $reply = Comment::factory()->for($story)->create(['parent_id' => $hiddenParent->id]);
    Comment::factory()->for($story)->create();
    $story->forceFill(['comment_count' => 1])->saveQuietly();

    $this->actingAs($reply->user)
        ->delete(route('comments.destroy', $reply))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($story->fresh()->comment_count)->toBe(1);
});

test('refresh recounts ratings favorites and comments in bulk', function () {
    $storyA = Story::factory()->create();
    $storyB = Story::factory()->create();

    // RatingFactory/FavoriteFactory không tự sinh user_id nên phải gắn
    // user rõ ràng; ratings có unique(user_id, story_id) nên 2 lượt
    // đánh giá phải là 2 user khác nhau (fix2).
    $rater1 = User::factory()->create();
    $rater2 = User::factory()->create();

    Rating::factory()->for($rater1)->for($storyA)->create(['score' => 4]);
    Rating::factory()->for($rater2)->for($storyA)->create(['score' => 5]);
    Favorite::factory()->for($rater1)->for($storyA)->create();
    Comment::factory()->for($storyA)->create();
    Comment::factory()->for($storyA)->create(['is_hidden' => true]);

    foreach ([$storyA, $storyB] as $story) {
        $story->forceFill([
            'favorite_count' => 99,
            'rating_count' => 99,
            'average_rating' => 9.99,
            'comment_count' => 99,
        ])->saveQuietly();
    }

    app(StoryStatsService::class)->refreshStories([$storyA->id, $storyB->id]);

    $storyA = $storyA->fresh();
    expect($storyA->favorite_count)->toBe(1)
        ->and($storyA->rating_count)->toBe(2)
        ->and((float) $storyA->average_rating)->toBe(4.5)
        ->and($storyA->comment_count)->toBe(1);

    $storyB = $storyB->fresh();
    expect($storyB->favorite_count)->toBe(0)
        ->and($storyB->rating_count)->toBe(0)
        ->and((float) $storyB->average_rating)->toBe(0.0)
        ->and($storyB->comment_count)->toBe(0);
});

test('refresh chapter comments counts only visible comments', function () {
    $story = Story::factory()->create();
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id, 'is_hidden' => true]);
    $chapter->forceFill(['comment_count' => 99])->saveQuietly();

    app(StoryStatsService::class)->refreshChapterComments([$chapter->id]);

    expect($chapter->fresh()->comment_count)->toBe(1);
});
