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
