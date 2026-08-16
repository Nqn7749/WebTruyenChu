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
        $this->clearHomeCache();
    }

    public function deleted(Story $story): void
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
     * Xóa cache trang chủ. Với "recently_updated" (có phân trang) chỉ cần
     * xóa vài trang đầu vì TTL 10 phút đã tự làm mới các trang sau.
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