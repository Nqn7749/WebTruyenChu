<?php

namespace App\Observers;

use App\Models\Chapter;
use App\Services\HomeCacheService;
use App\Services\SitemapCacheService;
use Illuminate\Support\Facades\DB;

class ChapterObserver
{
    private const SITEMAP_FIELDS = [
        'story_id',
        'slug',
        'status',
        'published_at',
        // Sửa nội dung chương cũng phải làm mới sitemap: lastmod của
        // chương lấy từ updated_at nên cache cũ sẽ giữ lastmod cũ
        // nếu không invalidate ở đây.
        'content',
    ];

    public function saved(Chapter $chapter): void
    {
        $changes = array_keys($chapter->getChanges());
        $relevantChanges = array_intersect($changes, self::SITEMAP_FIELDS);

        if ($chapter->wasRecentlyCreated) {
            if ($chapter->status) {
                app(SitemapCacheService::class)->invalidate();
                app(HomeCacheService::class)->invalidate();
            }

            return;
        }

        if ($relevantChanges !== []) {
            app(SitemapCacheService::class)->invalidate();

            app(HomeCacheService::class)->invalidate();
        }
    }

    public function deleted(Chapter $chapter): void
    {
        if ($chapter->status) {
            app(SitemapCacheService::class)->invalidate();
            app(HomeCacheService::class)->invalidate();
        }
    }

    public function restored(Chapter $chapter): void
    {
        if ($chapter->status) {
            app(SitemapCacheService::class)->invalidate();
            app(HomeCacheService::class)->invalidate();
        }
    }

    public function forceDeleted(Chapter $chapter): void
    {
        if ($chapter->status) {
            app(SitemapCacheService::class)->invalidate();
            app(HomeCacheService::class)->invalidate();
        }

        // Xóa vĩnh viễn chương thì thông báo về chương đó không còn mở
        // được nữa (P-M2).
        DB::table('notifications')
            ->whereJsonContains('data->chapter_id', $chapter->id)
            ->delete();
    }
}
