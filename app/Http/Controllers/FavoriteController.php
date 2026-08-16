<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function toggle(Story $story)
    {
        $userId = Auth::id();
        $message = null;

        DB::transaction(function () use ($story, $userId, &$message) {
            $lockedStory = Story::whereKey($story->id)->lockForUpdate()->first();

            $favorite = $lockedStory->favorites()->where('user_id', $userId)->first();

            if ($favorite) {
                $favorite->delete();
                $message = 'Đã bỏ yêu thích truyện.';
            } else {
                $lockedStory->favorites()->create([
                    'user_id' => $userId,
                    'notify_new_chapter' => true,
                ]);

                $toggleNotifyUrl = route('favorites.toggle-notify', $lockedStory);
                $message = 'Đã thêm vào truyện yêu thích. Bạn sẽ nhận thông báo khi có chương mới. '
                    . '<a href="' . $toggleNotifyUrl . '" class="alert-link">Tắt thông báo</a>';
            }

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

    public function toggleNotify(Story $story): RedirectResponse
    {
        $favorite = Auth::user()->favorites()->where('story_id', $story->id)->firstOrFail();

        $favorite->update(['notify_new_chapter' => ! $favorite->notify_new_chapter]);

        return back()->with('success', $favorite->notify_new_chapter
            ? 'Đã bật thông báo chương mới.'
            : 'Đã tắt thông báo chương mới. Bạn có thể bật lại bất cứ lúc nào trong trang "Yêu thích".');

    }
}