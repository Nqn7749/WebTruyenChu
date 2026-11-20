<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Services\HomeCacheService;
use App\Services\StoryStatsService;
use Illuminate\Support\Facades\Cache;

test('homepage cache invalidation initializes and increments the recently updated version', function () {
    Cache::forget('home:recently_updated:version');
    Cache::put('home:hot', collect(['stale']));

    app(HomeCacheService::class)->invalidate();

    expect(Cache::get('home:recently_updated:version'))->toBe(2)
        ->and(Cache::has('home:hot'))->toBeFalse();

    app(HomeCacheService::class)->invalidate();

    expect(Cache::get('home:recently_updated:version'))->toBe(3);
});

test('publishing a chapter invalidates homepage recently updated data after story stats refresh', function () {
    Cache::forget('home:recently_updated:version');

    $story = Story::factory()->create();
    $chapter = Chapter::factory()->for($story)->create([
        'status' => false,
        'published_at' => null,
    ]);

    $before = (int) Cache::get('home:recently_updated:version', 0);

    $chapter->update(['status' => true]);
    app(StoryStatsService::class)->refreshChapterStats($story->fresh());

    expect((int) Cache::get('home:recently_updated:version'))->toBeGreaterThan($before)
        ->and($story->fresh()->last_chapter_at)->not->toBeNull();
});

test('deleting and restoring a published chapter invalidates homepage data', function () {
    Cache::forget('home:recently_updated:version');

    $story = Story::factory()->create();
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    $afterCreate = (int) Cache::get('home:recently_updated:version');
    $chapter->delete();
    $afterDelete = (int) Cache::get('home:recently_updated:version');

    $chapter->restore();
    $afterRestore = (int) Cache::get('home:recently_updated:version');

    expect($afterDelete)->toBeGreaterThan($afterCreate)
        ->and($afterRestore)->toBeGreaterThan($afterDelete);
});
