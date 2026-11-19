<?php

namespace App\Observers;

use App\Models\Story;
use App\Services\SitemapCacheService;
use Illuminate\Support\Facades\Cache;

class StoryObserver
{
    private const IGNORED_FIELDS = [
        'views',
        'last_view_at',
        'favorite_count',
        'rating_count',
        'average_rating',
        'comment_count',
        'updated_at',
    ];

    public function saved(Story $story): void
    {
        $relevantChanges = array_diff(
            array_keys($story->getChanges()),
            self::IGNORED_FIELDS
        );

        if (! $story->wasRecentlyCreated && $relevantChanges === []) {
            return;
        }

        app(SitemapCacheService::class)->invalidate();
        $this->clearHomeCache();
    }

    public function deleted(Story $story): void
    {
        app(SitemapCacheService::class)->invalidate();
        $this->clearHomeCache();
    }

    public function restored(Story $story): void
    {
        app(SitemapCacheService::class)->invalidate();
        $this->clearHomeCache();
    }

    private function clearHomeCache(): void
    {
        Cache::forget('home:hot');
        Cache::forget('home:recommended');
        Cache::forget('home:newest');
        Cache::forget('home:completed');

        for ($page = 1; $page <= 3; $page++) {
            Cache::forget("home:recently_updated:page:{$page}");
        }
    }
}
