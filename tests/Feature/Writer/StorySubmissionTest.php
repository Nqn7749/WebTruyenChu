<?php

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;

test('a verified member can submit a story but cannot publish it directly', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện do thành viên đăng',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
        'status_publish' => true,
        'is_hot' => true,
    ])->assertRedirect(route('writer.stories.index'));

    $story = Story::query()->where('title', 'Truyện do thành viên đăng')->firstOrFail();
    expect($story->user_id)->toBe($user->id)
        ->and($story->status_publish)->toBeFalse()
        ->and($story->is_hot)->toBeFalse();
});

test('a member can only manage their own stories', function () {
    $owner = User::factory()->create(['status' => true]);
    $otherUser = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($owner)->get(route('writer.stories.edit', $story))->assertForbidden();
    $this->actingAs($owner)->delete(route('writer.stories.destroy', $story))->assertForbidden();

    expect($story->fresh())->not->toBeNull();
});

test('editing an approved story sends it back to moderation', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();
    $story = Story::factory()->create(['user_id' => $user->id, 'status_publish' => true]);

    $this->actingAs($user)->put(route('writer.stories.update', $story), [
        'title' => $story->title,
        'author_name' => $story->author_name,
        'description' => 'Nội dung vừa cập nhật',
        'status' => 'ongoing',
        'categories' => [$category->id],
    ])->assertRedirect(route('writer.stories.index'));

    expect($story->fresh()->status_publish)->toBeFalse();
});

test('a member can add a chapter only as a draft', function () {
    $user = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $user->id, 'status_publish' => true]);

    $this->actingAs($user)->post(route('writer.stories.chapters.store', $story), [
        'chapter_number' => 1,
        'title' => 'Chương đầu',
        'content' => 'Nội dung chương',
        'status' => true,
    ])->assertRedirect(route('writer.stories.chapters.index', $story));

    $chapter = Chapter::query()->where('story_id', $story->id)->firstOrFail();
    expect($chapter->status)->toBeFalse();
});
