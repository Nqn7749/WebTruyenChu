<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Public rankings, computed automatically from real activity. The ranked ids are cached for a few
 * minutes; nothing here is edited by hand.
 */
class RankingController extends Controller
{
    public const TYPES = [
        'doc' => ['label' => 'Đọc nhiều', 'unit' => 'lượt đọc'],
        'xem' => ['label' => 'Xem nhiều', 'unit' => 'lượt xem'],
        'yeu-thich' => ['label' => 'Yêu thích nhiều', 'unit' => 'lượt yêu thích'],
        'danh-gia' => ['label' => 'Đánh giá cao', 'unit' => 'điểm'],
    ];

    public const PERIODS = [
        'tuan' => ['label' => '7 ngày', 'days' => 7],
        'thang' => ['label' => '30 ngày', 'days' => 30],
        'tat-ca' => ['label' => 'Mọi thời gian', 'days' => null],
    ];

    private const LIMIT = 50;

    private const CACHE_MINUTES = 10;

    public function index(Request $request)
    {
        // Only plain strings count: ?loai[]=x (an array) must fall back to the default, not error.
        $type = $request->query('loai');
        $type = is_string($type) && array_key_exists($type, self::TYPES) ? $type : 'doc';
        $period = $request->query('thoi-gian');
        $period = is_string($period) && array_key_exists($period, self::PERIODS) ? $period : 'tuan';

        // Ratings are only meaningful over the whole life of a story.
        if ($type === 'danh-gia') {
            $period = 'tat-ca';
        }

        $ranked = Cache::remember(
            "ranking:{$type}:{$period}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->rank($type, $period),
        );

        $stories = Story::published()
            ->whereIn('id', array_keys($ranked))
            ->with('categories')
            ->get()
            ->keyBy('id');

        // Keep the ranked order and drop stories that were unpublished since the cache was filled.
        $rows = [];
        foreach ($ranked as $id => $score) {
            if ($story = $stories->get($id)) {
                $rows[] = ['story' => $story, 'score' => $score];
            }
        }

        return view('ranking.index', [
            'rows' => $rows,
            'type' => $type,
            'period' => $period,
            'types' => self::TYPES,
            'periods' => self::PERIODS,
        ]);
    }

    /**
     * @return array<int, float|int> story id => score, best first
     */
    private function rank(string $type, string $period): array
    {
        $days = self::PERIODS[$period]['days'];

        if ($type === 'danh-gia') {
            // A handful of ratings is not a reliable ranking: same rule as the "top rated" search sort.
            return Story::published()
                ->where('rating_count', '>=', 5)
                ->orderByDesc('average_rating')
                ->orderByDesc('rating_count')
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->pluck('average_rating', 'id')
                ->map(fn ($score) => round((float) $score, 1))
                ->all();
        }

        if ($days === null) {
            $column = ['doc' => 'reads_count', 'xem' => 'views', 'yeu-thich' => 'favorite_count'][$type];

            return Story::published()
                ->where($column, '>', 0)
                ->orderByDesc($column)
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->pluck($column, 'id')
                ->map(fn ($score) => (int) $score)
                ->all();
        }

        $since = today()->subDays($days - 1);

        if ($type === 'yeu-thich') {
            $query = DB::table('favorites as f')
                ->join('stories as s', 's.id', '=', 'f.story_id')
                ->where('f.created_at', '>=', $since)
                ->selectRaw('f.story_id as story_id, COUNT(*) as score')
                ->groupBy('f.story_id');
        } else {
            $column = $type === 'doc' ? 'reads' : 'views';
            $query = DB::table('story_daily_stats as d')
                ->join('stories as s', 's.id', '=', 'd.story_id')
                ->where('d.stat_date', '>=', $since->toDateString())
                ->selectRaw("d.story_id as story_id, SUM(d.{$column}) as score")
                ->groupBy('d.story_id')
                ->havingRaw("SUM(d.{$column}) > 0");
        }

        return $query
            ->where('s.status_publish', true)
            ->whereNull('s.deleted_at')
            ->orderByDesc('score')
            ->orderByDesc('story_id')
            ->limit(self::LIMIT)
            ->pluck('score', 'story_id')
            ->map(fn ($score) => (int) $score)
            ->all();
    }
}
