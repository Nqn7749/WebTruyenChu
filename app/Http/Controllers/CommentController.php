<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Story $story)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['story_id'] = $story->id;

        if (! empty($data['parent_id'])) {
            $parent = Comment::findOrFail($data['parent_id']);
            abort_unless($parent->story_id === $story->id, 404);
        }

        $comment = Comment::create($data);

        $story->increment('comment_count');
        if ($comment->chapter_id) {
            $comment->chapter->increment('comment_count');
        }

        return back()->with('success', 'Đã gửi bình luận.');
    }

    public function destroy(Comment $comment)
    {
        abort_unless($comment->user_id === Auth::id(), 403);

        if (! $comment->is_hidden) {
            $comment->story->decrement('comment_count');
            if ($comment->chapter_id) {
                $comment->chapter->decrement('comment_count');
            }
        }

        $comment->delete();

        return back()->with('success', 'Đã xóa bình luận.');
    }
}