<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Observers\CategoryObserver;
use App\Observers\ChapterObserver;
use App\Observers\StoryObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Giới hạn số trang tối đa cho mọi paginate(): ?page=999999 bắt
        // DB chạy OFFSET khổng lồ (P-M4). 1000 trang vẫn đủ cho mọi
        // danh sách thực tế của site.
        Paginator::currentPageResolver(function ($pageName = 'page') {
            $value = request()->input($pageName, 1);

            if (! is_numeric($value)) {
                return 1;
            }

            return min(1000, max(1, (int) $value));
        });

        View::composer('layouts.public', function ($view) {
            $navCategories = Cache::remember('nav_categories', now()->addHours(6), function () {
                return Category::whereNull('parent_id')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']);
            });

            $view->with('navCategories', $navCategories);
        });

        Story::observe(StoryObserver::class);
        Chapter::observe(ChapterObserver::class);
        Category::observe(CategoryObserver::class);
    }
}
