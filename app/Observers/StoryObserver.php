<?php

namespace App\Observers;

use App\Models\Story;
use App\Services\HomeCacheService;
use App\Services\SitemapCacheService;

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
        app(HomeCacheService::class)->invalidate();
    }

    public function deleted(Story $story): void
    {
        app(SitemapCacheService::class)->invalidate();
        app(HomeCacheService::class)->invalidate();
    }

    public function forceDeleted(Story $story): void
    {
        // Giống ChapterObserver: xóa vĩnh viễn cũng phải làm mới cache,
        // nếu không truyện đã xóa vẫn nằm trong cache home và sitemap
        // (NL-1). Hiện chưa có controller nào gọi forceDelete() nên đây
        // là phòng thủ cho route gọi nó sau này.
        app(SitemapCacheService::class)->invalidate();
        app(HomeCacheService::class)->invalidate();
    }

    public function restored(Story $story): void
    {
        app(SitemapCacheService::class)->invalidate();
        app(HomeCacheService::class)->invalidate();
    }
}
