<?php

namespace App\Http\Controllers;

use App\Models\Story;

class HomeController extends Controller
{
    public function index()
    {
        $recentlyUpdated = Story::published()
            ->with('categories')
            ->recentlyUpdated()
            ->paginate(12, ['*'], 'recent');

        $hotStories = Story::published()
            ->with('categories')
            ->hot()
            ->take(10)
            ->get();

        $recommendedStories = Story::published()
            ->with('categories')
            ->recommended()
            ->take(10)
            ->get();

        return view('home', compact('recentlyUpdated', 'hotStories', 'recommendedStories'));
    }
}