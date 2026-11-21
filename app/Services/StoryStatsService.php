<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Story;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoryStatsService
{
    /**
     * Recalculate aggregate counters from their source records.
     *
     * Đếm gộp theo truyện (GROUP BY) thay vì 3 SELECT + 1 UPDATE cho
     * mỗi truyện: xóa tài khoản có nhiều bình luận trước đây chạy
     * 4 truy vấn/truyện trong một transaction (P-M7). Truyện không có
     * dòng nào trong kết quả gộp thì số đếm về 0.
     *
     * @param  Collection<int, int>|array<int, int>  $storyIds
     */
    public function refreshStories(Collection|array $storyIds): void
    {
        $ids = collect($storyIds)->unique()->values();

        foreach ($ids->chunk(100) as $chunk) {
            $ratings = Rating::query()
                ->whereIn('story_id', $chunk)
                ->selectRaw('story_id, COUNT(*) as total, COALESCE(AVG(score), 0) as avg_score')
                ->groupBy('story_id')
                ->get()
                ->keyBy('story_id');

            $favorites = Favorite::query()
                ->whereIn('story_id', $chunk)
                ->selectRaw('story_id, COUNT(*) as total')
                ->groupBy('story_id')
                ->get()
                ->keyBy('story_id');

            // Dùng cùng định nghĩa hiển thị với giao diện
            // (Comment::scopeVisible): reply của cha đã ẩn/xóa và
            // comment của chương đã xóa mềm hoặc đã gỡ công khai
            // không được đếm (C-M1, F10).
            $comments = Comment::query()
                ->whereIn('story_id', $chunk)
                ->visible()
                ->selectRaw('story_id, COUNT(*) as total')
                ->groupBy('story_id')
                ->get()
                ->keyBy('story_id');

            Story::withTrashed()
                ->whereIn('id', $chunk)
                // Khóa dòng truyện trong transaction của caller (xóa tài
                // khoản) để increment đồng thời (bình luận/đánh giá/yêu
                // thích) phải chờ, không bị bản đếm lại này ghi đè mất
                // (F2). Ngoài transaction thì lock chỉ giữ tới hết câu
                // lệnh nên vô hại.
                ->lockForUpdate()
                ->get()
                ->each(function (Story $story) use ($ratings, $favorites, $comments) {
                    $rating = $ratings->get($story->id);

                    // saveQuietly chỉ tắt event, vẫn cập nhật updated_at:
                    // mỗi lần đếm số lại làm sitemap tưởng truyện mới cập
                    // nhật và crawl lại vô ích (NL-4).
                    $story->timestamps = false;
                    $story->forceFill([
                        'favorite_count' => (int) ($favorites->get($story->id)?->total ?? 0),
                        'rating_count' => (int) ($rating?->total ?? 0),
                        'average_rating' => round((float) ($rating?->avg_score ?? 0), 2),
                        'comment_count' => (int) ($comments->get($story->id)?->total ?? 0),
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
     * Số lượng mà một comment đang đóng góp vào comment_count, theo đúng
     * định nghĩa hiển thị (xem Comment::scopeVisible): bản thân comment
     * (nếu đang hiển thị) + các reply còn hiển thị của nó. Mọi chỗ
     * tăng/giảm tay (ẩn/hiện, xóa comment, xóa chương) dùng chung hàm
     * này để không lệch với công thức đếm lại ở refreshStories (C-M1,
     * C-L2).
     */
    public static function countedCommentAmount(Comment $comment): int
    {
        if ($comment->is_hidden) {
            return 0;
        }

        // Reply của cha đã ẩn hoặc đã xóa không hiển thị trên giao diện
        // nên không được đếm (trước đây xóa/ẩn reply này vẫn trừ 1).
        if ($comment->parent_id !== null) {
            $parent = Comment::query()->withTrashed()->find($comment->parent_id);

            if ($parent === null || $parent->is_hidden || $parent->trashed()) {
                return 0;
            }
        }

        // Comment thuộc chương đã xóa mềm hoặc đã gỡ công khai cũng
        // không hiển thị ở đâu nữa (trang chương 404 khi status =
        // false) nên không được đếm (F10).
        if ($comment->chapter_id !== null) {
            $chapter = Chapter::query()->withTrashed()->find($comment->chapter_id);

            if ($chapter === null || $chapter->trashed() || ! $chapter->status) {
                return 0;
            }
        }

        return 1 + $comment->replies()->where('is_hidden', false)->count();
    }

    /**
     * Trừ comment_count nhưng không để âm. Dùng 2 câu lệnh để chạy được
     * cả MySQL lẫn SQLite (SQLite không có GREATEST()).
     *
     * Câu thứ hai chỉ chạy khi câu đầu không trừ được dòng nào (count
     * đã nhỏ hơn amount từ trước): đánh giá count sau khi trừ như
     * trước đây sẽ zero nhầm — count=5, amount=3 thì câu đầu trừ còn
     * 2, câu sau thấy 0 < 2 < 3 nên set về 0 (F1).
     */
    public static function decrementCommentCount(string $table, int $id, int $amount): void
    {
        $affected = DB::table($table)
            ->where('id', $id)
            ->where('comment_count', '>=', $amount)
            ->decrement('comment_count', $amount);

        if ($affected === 0) {
            DB::table($table)
                ->where('id', $id)
                ->where('comment_count', '>', 0)
                ->update(['comment_count' => 0]);
        }
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
            $comments = Comment::query()
                ->whereIn('chapter_id', $chunk)
                ->visible()
                ->selectRaw('chapter_id, COUNT(*) as total')
                ->groupBy('chapter_id')
                ->get()
                ->keyBy('chapter_id');

            Chapter::withTrashed()
                ->whereIn('id', $chunk)
                // Như refreshStories (F2): khóa dòng chương trong
                // transaction của caller để increment đồng thời không
                // bị bản đếm lại này ghi đè mất.
                ->lockForUpdate()
                ->get()
                ->each(function (Chapter $chapter) use ($comments) {
                    // Xem ghi chú ở refreshStories (NL-4).
                    $chapter->timestamps = false;
                    $chapter->forceFill([
                        'comment_count' => (int) ($comments->get($chapter->id)?->total ?? 0),
                    ])->saveQuietly();
                    $chapter->timestamps = true;
                });
        }
    }
}
