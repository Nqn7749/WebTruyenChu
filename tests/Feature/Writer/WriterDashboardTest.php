<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Models\User;

test('writer dashboard only reports stories owned by the signed in author', function () {
    $author = User::factory()->create(['status' => true]);
    $otherAuthor = User::factory()->create(['status' => true]);

    $ownStory = Story::factory()->create([
        'user_id' => $author->id,
        'views' => 123,
        'chapter_count' => 4,
        'comment_count' => 2,
    ]);
    Story::factory()->create([
        'user_id' => $otherAuthor->id,
        'title' => 'Truyện của người khác',
        'views' => 999,
    ]);

    $this->actingAs($author)
        ->get(route('writer.dashboard'))
        ->assertOk()
        ->assertSee('Tổng quan tác giả')
        ->assertSee($ownStory->title)
        ->assertDontSee('Truyện của người khác')
        ->assertSee('123');
});

test('a writer can preview their own unpublished chapter without publishing it', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id]);
    $chapter = Chapter::factory()->create([
        'story_id' => $story->id,
        'status' => false,
        'content' => 'Nội dung bản nháp cần xem trước',
    ]);

    $this->actingAs($author)
        ->get(route('writer.chapters.preview', $chapter))
        ->assertOk()
        ->assertSee('Chế độ xem trước.')
        ->assertSee('Nội dung bản nháp cần xem trước');

    expect($chapter->fresh()->status)->toBeFalse()
        ->and($chapter->fresh()->views)->toBe(0);
});

test('a writer cannot preview another authors chapter', function () {
    $author = User::factory()->create(['status' => true]);
    $otherAuthor = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $otherAuthor->id]);
    $chapter = Chapter::factory()->create(['story_id' => $story->id, 'status' => false]);

    $this->actingAs($author)
        ->get(route('writer.chapters.preview', $chapter))
        ->assertForbidden();
});

test('a writer can only moderate comments on their own stories', function () {
    $author = User::factory()->create(['status' => true]);
    $otherAuthor = User::factory()->create(['status' => true]);
    $commenter = User::factory()->create(['status' => true]);
    $ownStory = Story::factory()->create(['user_id' => $author->id, 'comment_count' => 1]);
    $otherStory = Story::factory()->create(['user_id' => $otherAuthor->id, 'comment_count' => 1]);
    $ownComment = Comment::factory()->create([
        'user_id' => $commenter->id,
        'story_id' => $ownStory->id,
        'is_hidden' => false,
    ]);
    $otherComment = Comment::factory()->create([
        'user_id' => $commenter->id,
        'story_id' => $otherStory->id,
        'is_hidden' => false,
    ]);

    $this->actingAs($author)
        ->get(route('writer.comments.index'))
        ->assertOk()
        ->assertSee($ownComment->content)
        ->assertDontSee($otherComment->content);

    $this->actingAs($author)
        ->patch(route('writer.comments.toggle-hidden', $ownComment))
        ->assertRedirect();

    expect($ownComment->fresh()->is_hidden)->toBeTrue()
        ->and($ownStory->fresh()->comment_count)->toBe(0);

    $this->patch(route('writer.comments.toggle-hidden', $otherComment))
        ->assertForbidden();

    expect($otherComment->fresh()->is_hidden)->toBeFalse()
        ->and($otherStory->fresh()->comment_count)->toBe(1);
});
