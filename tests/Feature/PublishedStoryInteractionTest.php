<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;

test('unpublished stories cannot receive comments ratings or favorites', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => false]);

    $this->actingAs($user)
        ->postJson(route('comments.store', $story), [
            'content' => 'A comment',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('ratings.store', $story), [
            'score' => 5,
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('favorites.toggle', $story))
        ->assertNotFound();
});

test('comments cannot be attached to an unpublished chapter', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => false]);

    $this->actingAs($user)
        ->postJson(route('comments.store', $story), [
            'chapter_id' => $chapter->id,
            'content' => 'A comment',
        ])
        ->assertNotFound();
});

test('an author cannot rate their own story', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);

    $this->actingAs($author)
        ->post(route('ratings.store', $story), ['score' => 5])
        ->assertForbidden();

    expect($story->fresh()->rating_count)->toBe(0);
});

test('an author cannot favorite their own story', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);

    $this->actingAs($author)
        ->post(route('favorites.toggle', $story))
        ->assertForbidden();

    expect($story->fresh()->favorite_count)->toBe(0);
});
