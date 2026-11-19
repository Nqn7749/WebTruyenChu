<?php

namespace App\Observers;

use App\Models\Chapter;
use App\Services\SitemapCacheService;

class ChapterObserver
{
    private const SITEMAP_FIELDS = [
        'story_id',
        'slug',
        'status',
        'published_at',
    ];

    public function saved(Chapter $chapter): void
    {
        $relevantChanges = array_intersect(
            array_keys($chapter->getChanges()),
            self::SITEMAP_FIELDS
        );

        if ($chapter->wasRecentlyCreated) {
            if ($chapter->status) {
                app(SitemapCacheService::class)->invalidate();
            }

            return;
        }

        if ($relevantChanges !== []) {
            app(SitemapCacheService::class)->invalidate();
        }
    }

    public function deleted(Chapter $chapter): void
    {
        if ($chapter->status) {
            app(SitemapCacheService::class)->invalidate();
        }
    }

    public function restored(Chapter $chapter): void
    {
        if ($chapter->status) {
            app(SitemapCacheService::class)->invalidate();
        }
    }

    public function forceDeleted(Chapter $chapter): void
    {
        if ($chapter->status) {
            app(SitemapCacheService::class)->invalidate();
        }
    }
}
