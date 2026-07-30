<?php

namespace App\Http\Controllers;

use App\Models\Category;

class CategoryController extends Controller
{
    public function show(Category $category)
    {
        $stories = $category->stories()
            ->published()
            ->with('categories')
            ->latest('last_chapter_at')
            ->paginate(15);

        return view('categories.show', compact('category', 'stories'));
    }
}