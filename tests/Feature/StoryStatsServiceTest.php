<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryStatsService;

test('chapter statistics count drafts and track the newest published chapter', function () {
    $story = Story::factory()->create();
    $published = Chapter::factory()->for($story)->create([
        'chapter_number' => 1,
        'status' => true,
        'published_at' => now()->subDay(),
    ]);
    Chapter::factory()->for($story)->create([
        'chapter_number' => 2,
        'status' => false,
    ]);

    app(StoryStatsService::class)->refreshChapterStats($story);

    expect($story->fresh()->chapter_count)->toBe(2);
    expect($story->fresh()->last_chapter_at->equalTo($published->published_at))->toBeTrue();

    $published->delete();
    app(StoryStatsService::class)->refreshChapterStats($story->fresh());

    expect($story->fresh()->chapter_count)->toBe(1);
    expect($story->fresh()->last_chapter_at)->toBeNull();
});

test('chapter views do not change its publication timestamp or story chapter timestamp', function () {
    $story = Story::factory()->create();
    $publishedAt = now()->subDays(2);
    $chapter = Chapter::factory()->for($story)->create([
        'status' => true,
        'published_at' => $publishedAt,
    ]);

    app(StoryStatsService::class)->refreshChapterStats($story);
    $lastChapterAt = $story->fresh()->last_chapter_at;

    $chapter->increment('views');
    app(StoryStatsService::class)->refreshChapterStats($story->fresh());

    expect($chapter->fresh()->published_at->equalTo($publishedAt))->toBeTrue();
    expect($story->fresh()->last_chapter_at->equalTo($lastChapterAt))->toBeTrue();
});

test('publishing a draft sets publication time and unpublishing clears it', function () {
    $story = Story::factory()->create();
    $chapter = Chapter::factory()->for($story)->create([
        'status' => false,
        'published_at' => null,
    ]);

    $chapter->update(['status' => true]);
    expect($chapter->fresh()->published_at)->not->toBeNull();

    $chapter->update(['status' => false]);
    expect($chapter->fresh()->published_at)->toBeNull();
});
