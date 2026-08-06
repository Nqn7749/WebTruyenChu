<?php

namespace App\View\Composers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CategoryNavComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $navCategories = Cache::remember('nav_categories', now()->addHours(6), function () {
            return Category::whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);
        });

        $view->with('navCategories', $navCategories);
    }
}