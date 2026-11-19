<?php

use App\Models\Comment;
use App\Models\Story;
use App\Models\User;

test('a reply cannot be nested below another reply', function () {
    $user = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['status_publish' => true]);

    $root = Comment::factory()->create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'parent_id' => null,
        'is_hidden' => false,
    ]);

    $reply = Comment::factory()->create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'parent_id' => $root->id,
        'is_hidden' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('comments.store', $story), [
            'parent_id' => $reply->id,
            'content' => 'Nested reply should be rejected.',
        ])
        ->assertUnprocessable();

    expect(Comment::query()->where('story_id', $story->id)->count())->toBe(2);
});
