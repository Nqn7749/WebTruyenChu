<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public const CACHE_TTL_MINUTES = 10;

    public function index()
    {
        $page = request('recent', 1);

        $recentlyUpdated = Cache::remember(
            "home:recently_updated:page:{$page}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => Story::published()
                ->with('categories')
                ->recentlyUpdated()
                ->paginate(20, ['*'], 'recent', $page)
        );

        $hotStories = Cache::remember(
            'home:hot',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => Story::published()->with('categories')->hot()->take(20)->get()
        );

        $recommendedStories = Cache::remember(
            'home:recommended',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => Story::published()->with('categories')->recommended()->take(20)->get()
        );

        $newStories = Cache::remember(
            'home:newest',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => Story::published()->with('categories')->newest()->take(20)->get()
        );

        $completedStories = Cache::remember(
            'home:completed',
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => Story::published()->with('categories')->completed()->take(20)->get()
        );

        return view('home', compact(
            'recentlyUpdated',
            'hotStories',
            'recommendedStories',
            'newStories',
            'completedStories'
        ));
    }
}