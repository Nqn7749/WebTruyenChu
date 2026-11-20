<?php

use App\Models\Chapter;
use App\Models\Favorite;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Support\Facades\Notification;

test('a writer can publish a new chapter immediately and notify followers', function () {
    Notification::fake();

    $author = User::factory()->create(['status' => true]);
    $follower = User::factory()->create(['status' => true]);
    $story = Story::factory()->create([
        'user_id' => $author->id,
        'status_publish' => true,
    ]);

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    $this->actingAs($author)
        ->post(route('writer.stories.chapters.store', $story), [
            'chapter_number' => 1,
            'title' => 'Chương mở đầu',
            'content' => 'Nội dung chương mở đầu.',
            'status' => 1,
        ])
        ->assertRedirect(route('writer.stories.chapters.index', $story))
        ->assertSessionHas('success', 'Đã đăng chương công khai thành công.');

    $chapter = Chapter::query()->where('story_id', $story->id)->firstOrFail();

    expect($chapter->status)->toBeTrue();

    Notification::assertSentTo($follower, NewChapterPublished::class);
});

test('a writer can keep a chapter as draft, publish it later, and unpublish it', function () {
    Notification::fake();

    $author = User::factory()->create(['status' => true]);
    $follower = User::factory()->create(['status' => true]);
    $story = Story::factory()->create([
        'user_id' => $author->id,
        'status_publish' => true,
    ]);

    Favorite::create([
        'user_id' => $follower->id,
        'story_id' => $story->id,
        'notify_new_chapter' => true,
    ]);

    $this->actingAs($author)
        ->post(route('writer.stories.chapters.store', $story), [
            'chapter_number' => 1,
            'title' => 'Bản nháp',
            'content' => 'Nội dung bản nháp.',
        ])
        ->assertRedirect();

    $chapter = Chapter::query()->where('story_id', $story->id)->firstOrFail();

    expect($chapter->status)->toBeFalse();

    $this->put(route('writer.chapters.update', $chapter), [
        'chapter_number' => 1,
        'title' => 'Chương đã xuất bản',
        'content' => 'Nội dung đã hoàn thiện.',
        'status' => 1,
    ])->assertRedirect();

    expect($chapter->fresh()->status)->toBeTrue();

    Notification::assertSentTo($follower, NewChapterPublished::class);

    $this->put(route('writer.chapters.update', $chapter), [
        'chapter_number' => 1,
        'title' => 'Chương đã xuất bản',
        'content' => 'Nội dung đã hoàn thiện.',
    ])->assertRedirect();

    expect($chapter->fresh()->status)->toBeFalse();
});

test('a writer cannot publish or edit another authors chapter', function () {
    $author = User::factory()->create(['status' => true]);
    $otherAuthor = User::factory()->create(['status' => true]);
    $story = Story::factory()->create([
        'user_id' => $otherAuthor->id,
        'status_publish' => true,
    ]);
    $chapter = Chapter::factory()->create([
        'story_id' => $story->id,
        'status' => false,
    ]);

    $this->actingAs($author)
        ->put(route('writer.chapters.update', $chapter), [
            'chapter_number' => $chapter->chapter_number,
            'title' => 'Không được phép',
            'content' => 'Nội dung.',
            'status' => 1,
        ])
        ->assertForbidden();

    expect($chapter->fresh()->status)->toBeFalse();
});

test('a chapter content longer than the limit is rejected', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);

    $this->actingAs($author)->post(route('writer.stories.chapters.store', $story), [
        'chapter_number' => 1,
        'title' => 'Chương quá dài',
        'content' => str_repeat('a', 500001),
        'status' => true,
    ])->assertSessionHasErrors('content');

    expect(Chapter::query()->where('story_id', $story->id)->exists())->toBeFalse();
});
