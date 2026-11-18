<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $stories = Story::query()
            ->where('user_id', Auth::id())
            ->withCount([
                'chapters',
                'comments as visible_comments_count' => fn ($query) => $query->where('is_hidden', false),
                'favorites',
                'readingHistories',
            ])
            ->withCount([
                'readingHistories as readers_last_30_days_count' => fn ($query) => $query->where('read_at', '>=', now()->subDays(30)),
            ])
            ->latest('updated_at')
            ->paginate(10);

        $totals = [
            'stories' => Story::query()->where('user_id', Auth::id())->count(),
            'views' => (int) Story::query()->where('user_id', Auth::id())->sum('views'),
            'chapters' => (int) Story::query()->where('user_id', Auth::id())->sum('chapter_count'),
            'comments' => (int) Story::query()->where('user_id', Auth::id())->sum('comment_count'),
        ];

        return view('writer.dashboard', compact('stories', 'totals'));
    }
}
