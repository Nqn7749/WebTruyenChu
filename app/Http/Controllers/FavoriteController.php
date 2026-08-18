<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * Bật / tắt yêu thích truyện.
     */
    public function toggle(Story $story)
    {
        $userId = Auth::id();
        $flash = [];

        DB::transaction(function () use ($story, $userId, &$flash) {

            $lockedStory = Story::whereKey($story->id)
                ->lockForUpdate()
                ->first();

            $favorite = $lockedStory
                ->favorites()
                ->where('user_id', $userId)
                ->first();

            if ($favorite) {

                $favorite->delete();

                $flash = [
                    'type' => 'removed',
                ];

            } else {

                $lockedStory->favorites()->create([
                    'user_id' => $userId,
                    'notify_new_chapter' => true,
                ]);

                $flash = [
                    'type' => 'added',
                    'story_slug' => $lockedStory->slug,
                ];
            }

            // Cập nhật lại số lượng yêu thích thực tế
            $realCount = $lockedStory
                ->favorites()
                ->count();

            $lockedStory->forceFill([
                'favorite_count' => $realCount,
            ])->save();
        });

        return back()->with('favorite_flash', $flash);
    }


    /**
     * Danh sách truyện yêu thích.
     */
    public function index()
    {
        $stories = Auth::user()
            ->favorites()
            ->with('story.categories')
            ->latest()
            ->paginate(15);

        return view('favorites.index', compact('stories'));
    }


    /**
     * Bật / tắt thông báo chương mới.
     *
     * AJAX request - không reload trang.
     */
    public function toggleNotify(Story $story)
    {
        $favorite = Auth::user()
            ->favorites()
            ->where('story_id', $story->id)
            ->firstOrFail();

        $favorite->update([
            'notify_new_chapter' => ! $favorite->notify_new_chapter,
        ]);

        return response()->json([
            'success' => true,
            'notify_new_chapter' => (bool) $favorite->notify_new_chapter,
            
        ]);
    }
}