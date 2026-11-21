<?php

use App\Models\Comment;
use App\Models\Story;
use App\Models\User;

test('commenting twice within thirty seconds on the same story is rejected', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);

    $this->actingAs($user)
        ->postJson(route('comments.store', $story), ['content' => 'Bình luận đầu.'])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('comments.store', $story), ['content' => 'Bình luận thứ hai.'])
        ->assertStatus(429);

    expect(Comment::query()->where('story_id', $story->id)->count())->toBe(1);
});

test('posting the same content twice in a row on the same story is rejected', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);

    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'content' => 'Nội dung trùng.',
    ]);
    $comment->forceFill(['created_at' => now()->subMinutes(1)])->saveQuietly();

    $this->actingAs($user)
        ->postJson(route('comments.store', $story), ['content' => 'Nội dung trùng.'])
        ->assertUnprocessable();

    expect(Comment::query()->where('story_id', $story->id)->count())->toBe(1);
});

test('commenting on different stories within thirty seconds is rejected by the global cooldown', function () {
    $user = User::factory()->create();
    $storyA = Story::factory()->create(['status_publish' => true]);
    $storyB = Story::factory()->create(['status_publish' => true]);

    $this->actingAs($user)
        ->postJson(route('comments.store', $storyA), ['content' => 'Bình luận truyện A.'])
        ->assertOk();

    // Cooldown 30 giây tính theo user, không phân biệt truyện (round 6,
    // C2): đổi truyện khác không còn bypass được nữa.
    $this->actingAs($user)
        ->postJson(route('comments.store', $storyB), ['content' => 'Bình luận truyện B.'])
        ->assertStatus(429);

    expect(Comment::query()->count())->toBe(1);
});
