<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;

class CommentController extends Controller
{
    public function index()
    {
        $comments = Comment::with(['user', 'story'])
            ->latest()
            ->paginate(20);

        return view('admin.comments.index', compact('comments'));
    }

    public function toggleHidden(Comment $comment)
    {
        $comment->update(['is_hidden' => ! $comment->is_hidden]);

        $delta = $comment->is_hidden ? -1 : 1;
        $comment->story->increment('comment_count', $delta);

        if ($comment->chapter_id) {
            $comment->chapter->increment('comment_count', $delta);
        }

        return back()->with('success', 'Cập nhật trạng thái bình luận thành công.');
    }

    public function destroy(Comment $comment)
    {
        if (! $comment->is_hidden) {
            $comment->story->decrement('comment_count');
            if ($comment->chapter_id) {
                $comment->chapter->decrement('comment_count');
            }
        }

        $comment->delete();

        return back()->with('success', 'Xóa bình luận thành công.');
    }
}