<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRatingRequest;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    public function store(StoreRatingRequest $request, Story $story)
    {
        abort_unless($story->status_publish, 404);
        // Tác giả không tự đánh giá truyện của mình (tránh thổi điểm).
        abort_if($story->user_id === Auth::id(), 403);

        DB::transaction(function () use ($request, $story) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);

            $lockedStory->ratings()->updateOrCreate(
                ['user_id' => Auth::id()],
                ['score' => $request->validated('score')]
            );

            $stats = $lockedStory->ratings()
                ->selectRaw('COUNT(*) as total, COALESCE(AVG(score), 0) as avg_score')
                ->first();

            $lockedStory->forceFill([
                'rating_count' => (int) $stats->total,
                'average_rating' => round((float) $stats->avg_score, 2),
            ])->save();
        });

        return back()->with('success', 'Cảm ơn bạn đã đánh giá truyện.');
    }
}
