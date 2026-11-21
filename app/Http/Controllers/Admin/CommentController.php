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

            // Số đếm chỉ đổi đúng phần đóng góp hiển thị của comment này
            // trước và sau khi đổi trạng thái: ẩn cha cũng ẩn các reply
            // của nó trên giao diện nên số đếm đổi theo cả reply; reply
            // của cha đã ẩn thì vốn không được đếm nên ẩn/hiện nó không
            // đổi số đếm (C-L2). Trừ có chặn ở 0 khi số đếm đã lệch
            // trước đó (NM-4).
            $amountBefore = StoryStatsService::countedCommentAmount($locked);
            $locked->is_hidden = ! $locked->is_hidden;
            $locked->save();
            $amountAfter = StoryStatsService::countedCommentAmount($locked);
            $delta = $amountAfter - $amountBefore;

            if ($delta !== 0) {
                if ($locked->story) {
                    if ($delta < 0) {
                        StoryStatsService::decrementCommentCount('stories', $locked->story_id, -$delta);
                    } else {
                        DB::table('stories')->where('id', $locked->story_id)->increment('comment_count', $delta);
                    }
                }

                if ($locked->chapter_id && $locked->chapter) {
                    if ($delta < 0) {
                        StoryStatsService::decrementCommentCount('chapters', $locked->chapter_id, -$delta);
                    } else {
                        DB::table('chapters')->where('id', $locked->chapter_id)->increment('comment_count', $delta);
                    }
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

            // Chỉ trừ đúng phần đang được đếm (xem CommentController@destroy).
            $amount = StoryStatsService::countedCommentAmount($locked);

            if ($amount > 0) {
                StoryStatsService::decrementCommentCount('stories', $locked->story_id, $amount);
                if ($locked->chapter_id) {
                    StoryStatsService::decrementCommentCount('chapters', $locked->chapter_id, $amount);
                }
            }

            $locked->delete();
            $logger->record($request, 'comment.deleted', $locked, 'Xóa bình luận #'.$locked->id);
        });

        return back()->with('success', 'Xóa bình luận thành công.');
    }
}
