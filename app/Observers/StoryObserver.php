<?php

namespace App\Observers;

use App\Http\Controllers\SitemapController;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;

class StoryObserver
{
    /**
     * Các cột counter/cache: thay đổi chúng KHÔNG ảnh hưởng nội dung
     * hiển thị trên trang chủ hay sitemap, nên không cần xóa cache.
     */
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

        // Story mới tạo: getChanges() rỗng nên phải kiểm tra riêng.
        if (! $story->wasRecentlyCreated && $relevantChanges === []) {
            return;
        }

        $this->clearSitemapCache();
        $this->clearHomeCache();
    }

    public function deleted(Story $story): void
    {
        $this->clearSitemapCache();
        $this->clearHomeCache();
    }

    public function restored(Story $story): void
    {
        $this->clearSitemapCache();
        $this->clearHomeCache();
    }

    private function clearSitemapCache(): void
    {
        Cache::forget('sitemap:index');

        $totalStories = Story::published()->count();
        $totalPages = max(1, (int) ceil($totalStories / SitemapController::STORIES_PER_PAGE));

        for ($page = 1; $page <= $totalPages; $page++) {
            Cache::forget("sitemap:stories:{$page}");
        }
    }

    /**
     * Xóa cache trang chủ. "recently_updated" có phân trang nên chỉ xóa vài
     * trang đầu, TTL 10 phút sẽ tự làm mới các trang sau.
     */
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