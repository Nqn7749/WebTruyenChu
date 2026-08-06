<?php

namespace App\Providers;

use App\Models\Category;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Paginator::useBootstrapFive();
        View::composer('layouts.public', function ($view) {
            $navCategories = Cache::remember('nav_categories', now()->addHours(6), function () {
                return Category::whereNull('parent_id')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']);
            });

            $view->with('navCategories', $navCategories);
        });

    }
}
