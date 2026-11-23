<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\ReadingHistory;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Rows loaded for the "top stories" and "top authors" cards. The cards show the first five and
     * reveal the rest with "Xem thêm", without another request.
     */
    private const EXPANDED_ROWS = 20;

    public function __invoke()
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        $readCounts = ReadingHistory::query()
            ->whereBetween('read_at', [$startDate, $endDate])
            ->selectRaw('DATE(read_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $memberCounts = User::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyActivity = collect(range(29, 0))->map(function (int $daysAgo) use ($readCounts, $memberCounts) {
            $date = now()->startOfDay()->subDays($daysAgo);
            $key = $date->toDateString();

            return [
                'date' => $key,
                'label' => $date->format('d/m'),
                'reads' => (int) ($readCounts[$key] ?? 0),
                'members' => (int) ($memberCounts[$key] ?? 0),
            ];
        });

        $topStories = Story::query()
            ->published()
            ->with('user:id,name')
            ->orderByDesc('views')
            ->orderByDesc('id')
            ->limit(self::EXPANDED_ROWS)
            ->get(['id', 'user_id', 'title', 'slug', 'views', 'average_rating']);

        $topAuthors = DB::table('stories')
            ->join('users', 'users.id', '=', 'stories.user_id')
            ->whereNull('stories.deleted_at')
            ->where('stories.status_publish', true)
            ->selectRaw('users.id, users.name, COUNT(stories.id) as stories_count, SUM(stories.views) as views_total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('views_total')
            ->orderBy('users.id')
            ->limit(self::EXPANDED_ROWS)
            ->get();

        return view('admin.dashboard', [
            'storyCount' => Story::count(),
            'publishedStoryCount' => Story::published()->count(),
            'chapterCount' => Chapter::count(),
            'userCount' => User::count(),
            'activeUserCount' => User::query()->where('status', true)->count(),
            'newUserCount' => User::query()->where('created_at', '>=', $startDate)->count(),
            'commentCount' => Comment::query()->where('is_hidden', false)->count(),
            'viewCount' => (int) Story::published()->sum('views'),
            'dailyActivity' => $dailyActivity,
            'topStories' => $topStories,
            'topAuthors' => $topAuthors,
        ]);
    }
}
