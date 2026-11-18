<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Models\Comment;
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
            $lockedComment->is_hidden = ! $lockedComment->is_hidden;
            $lockedComment->save();

            $delta = $lockedComment->is_hidden ? -1 : 1;
            $lockedComment->story?->increment('comment_count', $delta);

            if ($lockedComment->chapter_id) {
                $lockedComment->chapter?->increment('comment_count', $delta);
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
