<?php

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

test('category changes invalidate the navigation category cache', function () {
    Cache::put('nav_categories', collect(['stale']), now()->addHour());

    Category::create([
        'name' => 'Fantasy',
        'slug' => 'fantasy',
    ]);

    expect(Cache::has('nav_categories'))->toBeFalse();
});
