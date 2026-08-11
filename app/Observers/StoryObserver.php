<?php

namespace App\Observers;

use App\Http\Controllers\SitemapController;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;

class StoryObserver
{
    public function saved(Story $story): void
    {
        $this->clearSitemapCache();
    }

    public function deleted(Story $story): void
    {
        $this->clearSitemapCache();
    }

    /**
     * Xóa cache sitemap index và toàn bộ sitemap con (theo trang),
     * để sitemap luôn phản ánh đúng dữ liệu mới nhất thay vì
     * chờ hết TTL 1 giờ.
     */
    private function clearSitemapCache(): void
    {
        Cache::forget('sitemap:index');

        $totalStories = Story::published()->count();
        $totalPages = max(1, (int) ceil($totalStories / SitemapController::STORIES_PER_PAGE));

        for ($page = 1; $page <= $totalPages; $page++) {
            Cache::forget("sitemap:stories:{$page}");
        }
    }
}