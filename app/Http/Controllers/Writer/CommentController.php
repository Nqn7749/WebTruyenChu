<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Services\StoryStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function index(): View
    {
        $comments = Comment::query()
            ->whereHas('story', fn ($query) => $query->where('user_id', Auth::id()))
            ->with(['user', 'story', 'chapter'])
            ->latest('id')
            ->paginate(20);

        return view('writer.comments.index', compact('comments'));
    }

    public function toggleHidden(Comment $comment): RedirectResponse
    {
        $this->ensureOwnStory($comment);

        DB::transaction(function () use ($comment) {
            $lockedComment = Comment::query()->lockForUpdate()->findOrFail($comment->id);
            $wasHidden = (bool) $lockedComment->is_hidden;
            $isHidden = ! $wasHidden;

            // Số đếm chỉ đổi đúng phần đóng góp hiển thị của comment này
            // trước và sau khi đổi trạng thái (xem Admin
            // CommentController@toggleHidden, C-L2).
            $amountBefore = StoryStatsService::countedCommentAmount($lockedComment);

            DB::table('comments')
                ->where('id', $lockedComment->id)
                ->update(['is_hidden' => $isHidden]);

            // Đồng bộ model trong bộ nhớ để tính số đếm sau khi đổi.
            $lockedComment->is_hidden = $isHidden;
            $amountAfter = StoryStatsService::countedCommentAmount($lockedComment);
            $delta = $amountAfter - $amountBefore;

            if ($delta !== 0) {
                if ($lockedComment->story) {
                    if ($delta < 0) {
                        StoryStatsService::decrementCommentCount('stories', $lockedComment->story_id, -$delta);
                    } else {
                        DB::table('stories')->where('id', $lockedComment->story_id)->increment('comment_count', $delta);
                    }
                }

                if ($lockedComment->chapter_id && $lockedComment->chapter) {
                    if ($delta < 0) {
                        StoryStatsService::decrementCommentCount('chapters', $lockedComment->chapter_id, -$delta);
                    } else {
                        DB::table('chapters')->where('id', $lockedComment->chapter_id)->increment('comment_count', $delta);
                    }
                }
            }
        });

        return back()->with('success', 'Đã cập nhật trạng thái bình luận.');
    }

    private function ensureOwnStory(Comment $comment): void
    {
        abort_unless(
            $comment->story && $comment->story->user_id === Auth::id(),
            403
        );
    }
}
