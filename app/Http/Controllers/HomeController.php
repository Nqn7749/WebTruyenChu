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
            ->paginate(20, ['*'], 'recent');

        $hotStories = Story::published()
            ->with('categories')
            ->hot()
            ->take(20)
            ->get();

        $recommendedStories = Story::published()
            ->with('categories')
            ->recommended()
            ->take(20)
            ->get();

        $newStories = Story::published()
            ->with('categories')
            ->newest()
            ->take(20)
            ->get();

        $completedStories = Story::published()
            ->with('categories')
            ->completed()
            ->take(20)
            ->get();

        return view('home', compact(
            'recentlyUpdated',
            'hotStories',
            'recommendedStories',
            'newStories',
            'completedStories'
        ));
    }
}