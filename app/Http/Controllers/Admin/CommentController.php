<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Services\AdminActivityLogger;
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

            if ($locked->story) {
                $storyQuery = DB::table('stories')->where('id', $locked->story_id);
                if ($locked->is_hidden) {
                    $storyQuery->where('comment_count', '>', 0)->decrement('comment_count');
                } else {
                    $storyQuery->increment('comment_count');
                }
            }

            if ($locked->chapter_id && $locked->chapter) {
                $chapterQuery = DB::table('chapters')->where('id', $locked->chapter_id);
                if ($locked->is_hidden) {
                    $chapterQuery->where('comment_count', '>', 0)->decrement('comment_count');
                } else {
                    $chapterQuery->increment('comment_count');
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
                DB::table('stories')->where('id', $locked->story_id)->where('comment_count', '>', 0)->decrement('comment_count');
                if ($locked->chapter_id) {
                    DB::table('chapters')->where('id', $locked->chapter_id)->where('comment_count', '>', 0)->decrement('comment_count');
                }
            }

            $locked->delete();
            $logger->record($request, 'comment.deleted', $locked, 'Xóa bình luận #'.$locked->id);
        });

        return back()->with('success', 'Xóa bình luận thành công.');
    }
}
