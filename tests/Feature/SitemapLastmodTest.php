<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;

test('rating a story does not change its updated_at', function () {
    $author = User::factory()->create();
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $updatedAt = now()->subDays(5);
    $story->forceFill(['updated_at' => $updatedAt])->saveQuietly();
    $rater = User::factory()->create();

    $this->actingAs($rater)
        ->post(route('ratings.store', $story), ['score' => 5])
        ->assertRedirect();

    expect($story->fresh()->updated_at->toDateTimeString())->toBe($updatedAt->toDateTimeString());
    expect($story->fresh()->rating_count)->toBe(1);
});

test('favoriting a story does not change its updated_at', function () {
    $author = User::factory()->create();
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $updatedAt = now()->subDays(5);
    $story->forceFill(['updated_at' => $updatedAt])->saveQuietly();
    $fan = User::factory()->create();

    $this->actingAs($fan)
        ->post(route('favorites.toggle', $story))
        ->assertRedirect();

    expect($story->fresh()->updated_at->toDateTimeString())->toBe($updatedAt->toDateTimeString());
    expect($story->fresh()->favorite_count)->toBe(1);
});

test('commenting on a story does not change its updated_at', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $updatedAt = now()->subDays(5);
    $story->forceFill(['updated_at' => $updatedAt])->saveQuietly();
    $commenter = User::factory()->create();

    $this->actingAs($commenter)
        ->postJson(route('comments.store', $story), ['content' => 'Bình luận hay.'])
        ->assertOk();

    expect($story->fresh()->updated_at->toDateTimeString())->toBe($updatedAt->toDateTimeString());
    expect($story->fresh()->comment_count)->toBe(1);
});

test('sitemap story lastmod reflects the newest published chapter', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $publishedAt = now()->subDay();
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $chapter->forceFill(['published_at' => $publishedAt])->saveQuietly();
    $story->forceFill([
        'updated_at' => now()->subDays(5),
        'last_chapter_at' => $publishedAt,
    ])->saveQuietly();

    $response = $this->get(route('sitemap.stories', ['page' => 1]));

    $response->assertOk();
    $response->assertSee($publishedAt->toAtomString(), false);
});

test('sitemap rejects a page beyond the total page count', function () {
    Story::factory()->create(['status_publish' => true]);
    $story = Story::factory()->create(['status_publish' => true]);
    Chapter::factory()->for($story)->create(['status' => true]);

    $this->get(route('sitemap.stories', ['page' => 999999]))->assertNotFound();
    $this->get(route('sitemap.chapters', ['page' => 999999]))->assertNotFound();
});

test('editing a chapter content moves its sitemap lastmod', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $oldUpdatedAt = now()->subDays(5);
    $chapter->forceFill(['updated_at' => $oldUpdatedAt])->saveQuietly();

    $this->get(route('sitemap.chapters', ['page' => 1]))
        ->assertOk()
        ->assertSee($oldUpdatedAt->toAtomString(), false);

    $chapter->update(['content' => 'Nội dung chương đã được sửa.']);

    $newUpdatedAt = $chapter->fresh()->updated_at->toAtomString();

    $this->get(route('sitemap.chapters', ['page' => 1]))
        ->assertOk()
        ->assertSee($newUpdatedAt, false);
});
