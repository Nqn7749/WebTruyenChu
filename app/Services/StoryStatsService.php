<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

                    // saveQuietly chỉ tắt event, vẫn cập nhật updated_at:
                    // mỗi lần đếm số lại làm sitemap tưởng truyện mới cập
                    // nhật và crawl lại vô ích (NL-4).
                    $story->timestamps = false;
                    $story->forceFill([
                        'favorite_count' => $story->favorites()->count(),
                        'rating_count' => (int) $rating->total,
                        'average_rating' => round((float) $rating->avg_score, 2),
                        'comment_count' => $story->comments()->where('is_hidden', false)->count(),
                    ])->saveQuietly();
                    $story->timestamps = true;
                });
        }

        // saveQuietly bypasses StoryObserver nên phải invalidate tay, nếu
        // không số liệu rating/favorite/comment trên trang chủ lag tối đa 10 phút.
        if ($ids->isNotEmpty()) {
            app(HomeCacheService::class)->invalidate();
        }
    }

    /**
     * Trừ comment_count nhưng không để âm. Dùng 2 câu lệnh để chạy được
     * cả MySQL lẫn SQLite (SQLite không có GREATEST()).
     */
    public static function decrementCommentCount(string $table, int $id, int $amount): void
    {
        DB::table($table)
            ->where('id', $id)
            ->where('comment_count', '>=', $amount)
            ->decrement('comment_count', $amount);

        DB::table($table)
            ->where('id', $id)
            ->where('comment_count', '>', 0)
            ->where('comment_count', '<', $amount)
            ->update(['comment_count' => 0]);
    }

    /**
     * Recalculate chapter count and latest publication timestamp.
     * Draft chapters are included in chapter_count to preserve its existing meaning.
     */
    public function refreshChapterStats(Story $story): void
    {
        // Xem ghi chú ở refreshStories (NL-4). Khôi phục lại timestamps
        // sau khi lưu để caller còn giữ instance này không bị ảnh hưởng.
        $story->timestamps = false;
        $story->forceFill([
            'chapter_count' => $story->chapters()->count(),
            'last_chapter_at' => $story->chapters()
                ->where('status', true)
                ->max('published_at'),
        ])->saveQuietly();
        $story->timestamps = true;

        // saveQuietly intentionally bypasses StoryObserver. Explicitly invalidate
        // homepage data after the aggregate timestamp has actually been persisted.
        if ($story->wasChanged('last_chapter_at')) {
            app(HomeCacheService::class)->invalidate();
        }
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
                    // Xem ghi chú ở refreshStories (NL-4).
                    $chapter->timestamps = false;
                    $chapter->forceFill([
                        'comment_count' => $chapter->comments()->where('is_hidden', false)->count(),
                    ])->saveQuietly();
                    $chapter->timestamps = true;
                });
        }
    }
}
