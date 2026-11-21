<?php

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryStatsService;

test('F1: hiding a parent with two replies subtracts exactly three', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);

    $story = Story::factory()->create();
    $parent = Comment::factory()->for($story)->create();
    Comment::factory()->for($story)->create(['parent_id' => $parent->id]);
    Comment::factory()->for($story)->create(['parent_id' => $parent->id]);
    Comment::factory()->for($story)->create();
    Comment::factory()->for($story)->create();
    $story->forceFill(['comment_count' => 5])->saveQuietly();

    $this->actingAs($admin)
        ->patch(route('admin.comments.toggle-hidden', $parent))
        ->assertRedirect();

    // Ẩn cha có 2 reply: trừ 3, còn 2 — trước đây câu clamp thứ hai
    // set nhầm về 0 vì 0 < 2 < 3.
    expect($story->fresh()->comment_count)->toBe(2);
});

test('F1: decrementCommentCount keeps the remainder when count is larger than amount', function () {
    $story = Story::factory()->create();
    $story->forceFill(['comment_count' => 5])->saveQuietly();

    StoryStatsService::decrementCommentCount('stories', $story->id, 3);

    expect($story->fresh()->comment_count)->toBe(2);
});

test('F1: decrementCommentCount clamps to zero when count equals amount', function () {
    $story = Story::factory()->create();
    $story->forceFill(['comment_count' => 2])->saveQuietly();

    StoryStatsService::decrementCommentCount('stories', $story->id, 2);

    expect($story->fresh()->comment_count)->toBe(0);
});

test('F1: decrementCommentCount clamps to zero when count is smaller than amount', function () {
    $story = Story::factory()->create();
    $story->forceFill(['comment_count' => 1])->saveQuietly();

    StoryStatsService::decrementCommentCount('stories', $story->id, 3);

    expect($story->fresh()->comment_count)->toBe(0);
});

test('F1: decrementCommentCount keeps zero at zero', function () {
    $story = Story::factory()->create();
    $story->forceFill(['comment_count' => 0])->saveQuietly();

    StoryStatsService::decrementCommentCount('stories', $story->id, 1);

    expect($story->fresh()->comment_count)->toBe(0);
});

test('F10: refresh excludes comments of an unpublished chapter', function () {
    $story = Story::factory()->create();
    $chapter = Chapter::factory()->for($story)->create(['status' => false]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);

    $story->forceFill(['comment_count' => 2])->saveQuietly();

    app(StoryStatsService::class)->refreshStories([$story->id]);

    expect($story->fresh()->comment_count)->toBe(0);
});

test('F10: refresh still counts comments of a published chapter', function () {
    $story = Story::factory()->create();
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);

    $story->forceFill(['comment_count' => 0])->saveQuietly();

    app(StoryStatsService::class)->refreshStories([$story->id]);

    expect($story->fresh()->comment_count)->toBe(1);
});

test('V4: a story meta_description of 17,000 emoji is rejected', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện meta emoji quá dài',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
        // Emoji 4 byte/ký tự: 17.000 ký tự vượt giới hạn 16.000 của rule
        // (trước đây max:20000 cho qua rồi MySQL báo 500 vì tràn cột TEXT).
        'meta_description' => str_repeat('😀', 17000),
    ])->assertSessionHasErrors('meta_description');

    expect(Story::query()->where('title', 'Truyện meta emoji quá dài')->exists())->toBeFalse();
});

test('V4: a story meta_description of 16,000 emoji is accepted', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện meta emoji vừa đủ',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
        // 16.000 emoji × 4 byte = 64.000 byte < 65.535 byte của cột TEXT.
        'meta_description' => str_repeat('😀', 16000),
    ])->assertRedirect();

    expect(Story::query()->where('title', 'Truyện meta emoji vừa đủ')->exists())->toBeTrue();
});

test('V8: a story author_name containing angle brackets is rejected', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện tác giả lạ',
        'author_name' => 'Tên <b>đậm</b>',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
    ])->assertSessionHasErrors('author_name');

    expect(Story::query()->where('title', 'Truyện tác giả lạ')->exists())->toBeFalse();
});
