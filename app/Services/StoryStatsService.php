<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Support\Collection;

class StoryStatsService
{
    /**
     * Recalculate aggregate counters from their source records.
     *
     * @param  Collection<int, int>|array<int, int>  $storyIds
     */
    public function refreshStories(Collection|array $storyIds): void
    {
        $ids = collect($storyIds)->unique()->values();

        foreach ($ids->chunk(100) as $chunk) {
            Story::withTrashed()
                ->whereIn('id', $chunk)
                ->get()
                ->each(function (Story $story) {
                    $rating = $story->ratings()
                        ->selectRaw('COUNT(*) as total, COALESCE(AVG(score), 0) as avg_score')
                        ->first();

                    $story->forceFill([
                        'favorite_count' => $story->favorites()->count(),
                        'rating_count' => (int) $rating->total,
                        'average_rating' => round((float) $rating->avg_score, 2),
                        'comment_count' => $story->comments()->where('is_hidden', false)->count(),
                    ])->saveQuietly();
                });
        }
    }

    /**
     * Recalculate chapter count and latest published chapter timestamp.
     * Draft chapters are included in chapter_count to preserve its existing meaning.
     */
    public function refreshChapterStats(Story $story): void
    {
        $story->forceFill([
            'chapter_count' => $story->chapters()->count(),
            'last_chapter_at' => $story->chapters()
                ->where('status', true)
                ->max('updated_at'),
        ])->save();
    }

    /**
     * Recalculate comment_count for the given chapters.
     *
     * @param  Collection<int, int>|array<int, int>  $chapterIds
     */
    public function refreshChapterComments(Collection|array $chapterIds): void
    {
        $ids = collect($chapterIds)->unique()->values();

        foreach ($ids->chunk(100) as $chunk) {
            Chapter::withTrashed()
                ->whereIn('id', $chunk)
                ->get()
                ->each(function (Chapter $chapter) {
                    $chapter->forceFill([
                        'comment_count' => $chapter->comments()->where('is_hidden', false)->count(),
                    ])->saveQuietly();
                });
        }
    }
}
