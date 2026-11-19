<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\SitemapCacheService;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        $this->invalidate();
    }

    public function deleted(Category $category): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        Cache::forget('nav_categories');
        app(SitemapCacheService::class)->invalidate();
    }
}
