<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Services\AdminActivityLogger;
use App\Services\StoryStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function index()
    {
        $comments = Comment::with(['user', 'story'])->latest()->paginate(20);

        return view('admin.comments.index', compact('comments'));
    }

    public function toggleHidden(Request $request, Comment $comment, AdminActivityLogger $logger)
    {
        DB::transaction(function () use ($comment, $request, $logger) {
            $locked = Comment::query()->lockForUpdate()->findOrFail($comment->id);
            $locked->is_hidden = ! $locked->is_hidden;
            $locked->save();

            // Ẩn/hiện comment cha cũng ẩn/hiện các reply của nó trên giao
            // diện (reply chỉ load qua comment cha) nên số đếm phải đổi
            // theo cả reply, và không để âm (N-L3, NM-4).
            $amount = 1 + $locked->replies()->where('is_hidden', false)->count();

            if ($locked->story) {
                if ($locked->is_hidden) {
                    StoryStatsService::decrementCommentCount('stories', $locked->story_id, $amount);
                } else {
                    DB::table('stories')->where('id', $locked->story_id)->increment('comment_count', $amount);
                }
            }

            if ($locked->chapter_id && $locked->chapter) {
                if ($locked->is_hidden) {
                    StoryStatsService::decrementCommentCount('chapters', $locked->chapter_id, $amount);
                } else {
                    DB::table('chapters')->where('id', $locked->chapter_id)->increment('comment_count', $amount);
                }
            }

            $logger->record(
                $request,
                $locked->is_hidden ? 'comment.hidden' : 'comment.unhidden',
                $locked,
                'Thay đổi trạng thái bình luận #'.$locked->id
            );
        });

        return back()->with('success', 'Cập nhật trạng thái bình luận thành công.');
    }

    public function destroy(Request $request, Comment $comment, AdminActivityLogger $logger)
    {
        DB::transaction(function () use ($comment, $request, $logger) {
            $locked = Comment::query()->lockForUpdate()->findOrFail($comment->id);

            if (! $locked->is_hidden) {
                // Xem ghi chú ở CommentController@destroy (N-L3, NM-4).
                $decrement = 1 + $locked->replies()->where('is_hidden', false)->count();

                StoryStatsService::decrementCommentCount('stories', $locked->story_id, $decrement);
                if ($locked->chapter_id) {
                    StoryStatsService::decrementCommentCount('chapters', $locked->chapter_id, $decrement);
                }
            }

            $locked->delete();
            $logger->record($request, 'comment.deleted', $locked, 'Xóa bình luận #'.$locked->id);
        });

        return back()->with('success', 'Xóa bình luận thành công.');
    }
}
