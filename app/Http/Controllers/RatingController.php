<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRatingRequest;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function store(StoreRatingRequest $request, Story $story)
{
    $story->ratings()->updateOrCreate(
        ['user_id' => Auth::id(), 'story_id' => $story->id],
        ['score' => $request->validated('score')]
    );

    $stats = $story->ratings()
        ->selectRaw('COUNT(*) as total, AVG(score) as avg_score')
        ->first();

    $story->forceFill([
        'rating_count'   => (int) $stats->total,
        'average_rating' => round((float) $stats->avg_score, 2),
    ])->save();

    return back()->with('success', 'Cảm ơn bạn đã đánh giá truyện.');
}
}