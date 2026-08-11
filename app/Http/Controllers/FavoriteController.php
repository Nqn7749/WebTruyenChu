<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function toggle(Story $story)
    {
        $userId = Auth::id();
        $message = null;

        DB::transaction(function () use ($story, $userId, &$message) {
            // Khóa dòng story trong transaction để 2 request cùng lúc
            // (double-click, double-submit) không đọc/ghi favorite_count đè lên nhau.
            $lockedStory = Story::whereKey($story->id)->lockForUpdate()->first();

            $favorite = $lockedStory->favorites()->where('user_id', $userId)->first();

            if ($favorite) {
                $favorite->delete();
                $message = 'Đã bỏ yêu thích truyện.';
            } else {
                $lockedStory->favorites()->create(['user_id' => $userId]);
                $message = 'Đã thêm vào truyện yêu thích.';
            }

            // Luôn recompute bằng COUNT(*) thật thay vì increment/decrement thủ công
            // để favorite_count không bao giờ lệch khỏi dữ liệu thật trong bảng favorites.
            $realCount = $lockedStory->favorites()->count();

            $lockedStory->forceFill(['favorite_count' => $realCount])->save();
        });

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