<?php

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\SitemapCacheService;
use Illuminate\Support\Facades\Cache;

test('creating a published story invalidates the sitemap index and story pages', function () {
    $cache = app(SitemapCacheService::class);
    $oldIndexKey = $cache->key('index');
    $oldStoriesKey = $cache->key('stories:1');

    Cache::put($oldIndexKey, 'stale-index');
    Cache::put($oldStoriesKey, 'stale-stories');

    Story::factory()->create(['status_publish' => true]);

    expect($cache->key('index'))->not->toBe($oldIndexKey)
        ->and($cache->key('stories:1'))->not->toBe($oldStoriesKey);

    $this->get(route('sitemap.index'))
        ->assertOk()
        ->assertSee(route('sitemap.stories', ['page' => 1]));

    $this->get(route('sitemap.stories', ['page' => 1]))
        ->assertOk()
        ->assertSee('truyen');
});

test('publishing and deleting a chapter invalidates chapter sitemap pages', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create([
        'slug' => 'ban-nhap',
        'status' => false,
    ]);

    $this->get(route('sitemap.chapters', ['page' => 1]))
        ->assertOk()
        ->assertDontSee('ban-nhap');

    $chapter->update(['status' => true]);

    $this->get(route('sitemap.chapters', ['page' => 1]))
        ->assertOk()
        ->assertSee('ban-nhap');

    $chapter->delete();

    $this->get(route('sitemap.chapters', ['page' => 1]))
        ->assertOk()
        ->assertDontSee('ban-nhap');
});

test('updating a category invalidates the category sitemap', function () {
    $category = Category::factory()->create(['slug' => 'tien-hiep']);

    $this->get(route('sitemap.categories'))
        ->assertOk()
        ->assertSee('tien-hiep');

    $category->update(['slug' => 'kiem-hiep']);

    $this->get(route('sitemap.categories'))
        ->assertOk()
        ->assertSee('kiem-hiep')
        ->assertDontSee('tien-hiep');
});
