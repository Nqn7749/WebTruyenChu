<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Models\ReadingHistory;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $userId = Auth::id();
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        $stories = Story::query()
            ->where('user_id', $userId)
            ->withCount([
                'chapters',
                'comments as visible_comments_count' => fn ($query) => $query->where('is_hidden', false),
                'favorites',
                'readingHistories',
            ])
            ->withCount([
                'readingHistories as readers_last_30_days_count' => fn ($query) => $query->where('read_at', '>=', $startDate),
            ])
            ->latest('updated_at')
            ->paginate(10);

        $totals = [
            'stories' => Story::query()->where('user_id', $userId)->count(),
            'views' => (int) Story::query()->where('user_id', $userId)->sum('views'),
            'chapters' => (int) Story::query()->where('user_id', $userId)->sum('chapter_count'),
            'comments' => (int) Story::query()->where('user_id', $userId)->sum('comment_count'),
            'readers_30_days' => ReadingHistory::query()
                ->whereHas('story', fn ($query) => $query->where('user_id', $userId))
                ->where('read_at', '>=', $startDate)
                ->count(),
        ];

        $readCounts = ReadingHistory::query()
            ->whereHas('story', fn ($query) => $query->where('user_id', $userId))
            ->whereBetween('read_at', [$startDate, $endDate])
            ->selectRaw('DATE(read_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyActivity = collect(range(29, 0))->map(function (int $daysAgo) use ($readCounts) {
            $date = now()->startOfDay()->subDays($daysAgo);
            $key = $date->toDateString();

            return [
                'date' => $key,
                'label' => $date->format('d/m'),
                'reads' => (int) ($readCounts[$key] ?? 0),
            ];
        });

        return view('writer.dashboard', compact('stories', 'totals', 'dailyActivity'));
    }
}
