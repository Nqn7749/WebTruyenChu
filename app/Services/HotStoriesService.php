<?php

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\DB;

/**
 * Daily activity counters and the automatic "Hot" ranking.
 */
class HotStoriesService
{
    /**
     * Count one real view for today. Call inside the transaction that increments the lifetime counters.
     */
    public static function recordView(int $storyId): void
    {
        self::bump($storyId, 'views');
    }

    /**
     * Count one real read (15 s on the page and 25% scrolled) for today.
     */
    public static function recordRead(int $storyId): void
    {
        self::bump($storyId, 'reads');
    }

    private static function bump(int $storyId, string $column): void
    {
        DB::table('story_daily_stats')->upsert(
            [['story_id' => $storyId, 'stat_date' => today()->toDateString(), 'views' => $column === 'views' ? 1 : 0, 'reads' => $column === 'reads' ? 1 : 0]],
            ['story_id', 'stat_date'],
            [$column => DB::raw($column.' + 1')],
        );
    }

    /**
     * Mark the most active published stories as hot and unmark auto-hot stories that fell out.
     * Stories pinned by an admin (hot_override on/off) are left alone.
     *
     * @return array{marked: int, unmarked: int}
     */
    public function refresh(): array
    {
        $window = max(1, (int) config('stories.hot.window_days', 7));
        $limit = max(0, (int) config('stories.hot.limit', 20));
        $minScore = max(0, (int) config('stories.hot.min_score', 10));

        $topIds = $limit === 0 ? [] : DB::table('story_daily_stats as d')
            ->join('stories as s', 's.id', '=', 'd.story_id')
            ->where('d.stat_date', '>=', today()->subDays($window - 1)->toDateString())
            ->where('s.status_publish', true)
            ->whereNull('s.deleted_at')
            ->whereNull('s.hot_override')
            ->groupBy('d.story_id')
            ->havingRaw('SUM(d.reads) * 3 + SUM(d.views) >= ?', [$minScore])
            ->orderByRaw('SUM(d.reads) * 3 + SUM(d.views) DESC')
            ->orderByDesc('d.story_id')
            ->limit($limit)
            ->pluck('d.story_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $marked = 0;
        $unmarked = 0;

        DB::transaction(function () use ($topIds, &$marked, &$unmarked): void {
            // Counters are not content edits: keep updated_at (sitemap lastmod) untouched.
            $unmarked = Story::withoutTimestamps(fn () => Story::query()
                ->whereNull('hot_override')
                ->where('is_hot', true)
                ->when($topIds !== [], fn ($query) => $query->whereNotIn('id', $topIds))
                ->update(['is_hot' => false]));

            $marked = $topIds === [] ? 0 : Story::withoutTimestamps(fn () => Story::query()
                ->whereIn('id', $topIds)
                ->where('is_hot', false)
                ->update(['is_hot' => true]));
        });

        // Old rows are of no use once they leave the window (keep a little extra for debugging).
        DB::table('story_daily_stats')->where('stat_date', '<', today()->subDays($window + 23)->toDateString())->delete();

        if ($marked > 0 || $unmarked > 0) {
            app(HomeCacheService::class)->invalidate();
        }

        return ['marked' => $marked, 'unmarked' => $unmarked];
    }
}
