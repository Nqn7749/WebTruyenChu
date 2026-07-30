<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q'));
        $categorySlug = $request->query('category');

        $query = Story::published()->with('categories');

        if ($keyword !== '') {
            $query->search($keyword);
        }

        if ($categorySlug) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $categorySlug));
        }

        $stories = $query->latest()->paginate(15)->withQueryString();
        $categories = Category::whereNull('parent_id')->get();

        return view('search.index', compact('stories', 'keyword', 'categories', 'categorySlug'));
    }
}