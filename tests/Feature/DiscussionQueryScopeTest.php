<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Models\User;

test('story and chapter pages share one discussion thread', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create([
        'chapter_number' => 1,
        'status' => true,
    ]);

    // Comment::create() tôn trọng $fillable (user_id/is_hidden không nằm
    // trong đó) nên dùng factory — factory chạy unguarded.
    $storyComment = Comment::factory()->create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'chapter_id' => null,
        'parent_id' => null,
        'content' => 'Story discussion',
        'is_hidden' => false,
    ]);

    $chapterComment = Comment::factory()->create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'chapter_id' => $chapter->id,
        'parent_id' => null,
        'content' => 'Chapter discussion',
        'is_hidden' => false,
    ]);

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertViewHas('comments', fn ($comments) => $comments->pluck('id')->sort()->values()->all() === [$storyComment->id, $chapterComment->id]);

    $this->get(route('chapters.show', [$story, $chapter->slug]))
        ->assertOk()
        ->assertViewHas('comments', fn ($comments) => $comments->pluck('id')->sort()->values()->all() === [$storyComment->id, $chapterComment->id]);
});
