<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Models\User;

test('story and chapter pages only load comments for their own discussion scope', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create([
        'chapter_number' => 1,
        'status' => true,
    ]);

    $storyComment = Comment::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'chapter_id' => null,
        'parent_id' => null,
        'content' => 'Story discussion',
        'is_hidden' => false,
    ]);

    $chapterComment = Comment::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'chapter_id' => $chapter->id,
        'parent_id' => null,
        'content' => 'Chapter discussion',
        'is_hidden' => false,
    ]);

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertViewHas('comments', fn ($comments) => $comments->pluck('id')->all() === [$storyComment->id]);

    $this->get(route('chapters.show', [$story, $chapter->slug]))
        ->assertOk()
        ->assertViewHas('comments', fn ($comments) => $comments->pluck('id')->all() === [$chapterComment->id]);
});
