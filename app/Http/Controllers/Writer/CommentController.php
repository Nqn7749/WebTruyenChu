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

            DB::table('comments')
                ->where('id', $lockedComment->id)
                ->update(['is_hidden' => $isHidden]);

            // Ẩn/hiện comment cha cũng ẩn/hiện các reply của nó trên giao
            // diện nên số đếm phải đổi theo cả reply (N-L3), và không
            // để âm khi số đếm đã lệch trước đó (NM-4).
            $amount = 1 + $lockedComment->replies()->where('is_hidden', false)->count();

            if ($lockedComment->story) {
                if ($isHidden) {
                    StoryStatsService::decrementCommentCount('stories', $lockedComment->story_id, $amount);
                } else {
                    DB::table('stories')->where('id', $lockedComment->story_id)->increment('comment_count', $amount);
                }
            }

            if ($lockedComment->chapter_id && $lockedComment->chapter) {
                if ($isHidden) {
                    StoryStatsService::decrementCommentCount('chapters', $lockedComment->chapter_id, $amount);
                } else {
                    DB::table('chapters')->where('id', $lockedComment->chapter_id)->increment('comment_count', $amount);
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
