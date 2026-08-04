<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function toggle(Story $story)
    {
        $userId = Auth::id();

        $favorite = $story->favorites()->firstOrCreate(['user_id' => $userId]);

        if (! $favorite->wasRecentlyCreated) {
            $favorite->delete();
            $story->decrement('favorite_count');
            $message = 'Đã bỏ yêu thích truyện.';
        } else {
            $story->increment('favorite_count');
            $message = 'Đã thêm vào truyện yêu thích.';
        }

        return back()->with('success', $message);
    }

    public function index()
    {
        $stories = Auth::user()
            ->favorites()
            ->with('story.categories')
            ->latest()
            ->paginate(15);

        return view('favorites.index', compact('stories'));
    }
}