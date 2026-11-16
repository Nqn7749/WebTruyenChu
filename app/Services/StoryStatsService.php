<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Support\Collection;

class StoryStatsService
{
    /**
     * Tính lại counter của các truyện bằng COUNT/AVG thực tế.
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
                        'rating_count'   => (int) $rating->total,
                        'average_rating' => round((float) $rating->avg_score, 2),
                        'comment_count'  => $story->comments()->where('is_hidden', false)->count(),
                    ])->saveQuietly();
                });
        }
    }

    /**
     * Tính lại comment_count của các chương.
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