<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryStatsService;

test('chapter statistics count drafts and track the newest published chapter', function () {
    $story = Story::factory()->create();
    $published = Chapter::factory()->for($story)->create([
        'chapter_number' => 1,
        'status' => true,
        'updated_at' => now()->subDay(),
    ]);
    Chapter::factory()->for($story)->create([
        'chapter_number' => 2,
        'status' => false,
    ]);

    app(StoryStatsService::class)->refreshChapterStats($story);

    expect($story->fresh()->chapter_count)->toBe(2);
    expect($story->fresh()->last_chapter_at->equalTo($published->updated_at))->toBeTrue();

    $published->delete();
    app(StoryStatsService::class)->refreshChapterStats($story->fresh());

    expect($story->fresh()->chapter_count)->toBe(1);
    expect($story->fresh()->last_chapter_at)->toBeNull();
});
