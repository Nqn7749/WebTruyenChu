<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Services\StoryStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    /**
     * Store a new comment.
     */
    public function store(
        StoreCommentRequest $request,
        Story $story
    ): JsonResponse|RedirectResponse {
        abort_unless($story->status_publish, 404);

        $data = $request->validated();

        $data['story_id'] = $story->id;

        /*
        |--------------------------------------------------------------------------
        | Validate chapter
        |--------------------------------------------------------------------------
        */

        if (! empty($data['chapter_id'])) {
            $chapter = Chapter::query()
                ->whereKey($data['chapter_id'])
                ->where('story_id', $story->id)
                ->where('status', true)
                ->firstOrFail();
        }

        /*
        |--------------------------------------------------------------------------
        | Validate parent comment
        |--------------------------------------------------------------------------
        */

        if (! empty($data['parent_id'])) {
            $parent = Comment::findOrFail($data['parent_id']);

            abort_unless(
                $parent->story_id === $story->id && ! $parent->is_hidden,
                404
            );

            // The discussion UI supports one reply level only.
            abort_unless(
                $parent->parent_id === null,
                422,
                'Không thể trả lời một bình luận trả lời khác.'
            );

            if ($parent->chapter_id) {
                abort_unless(
                    Chapter::query()
                        ->whereKey($parent->chapter_id)
                        ->where('story_id', $story->id)
                        ->where('status', true)
                        ->exists(),
                    404
                );
            }

            // Reply luôn thuộc cùng chapter với comment cha.
            $data['chapter_id'] = $parent->chapter_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Create comment
        |--------------------------------------------------------------------------
        */

        $comment = DB::transaction(function () use ($data, $story) {

            $comment = Comment::create($data);

            // user_id không nằm trong $fillable của Comment nên gán riêng ở đây.
            $comment->forceFill(['user_id' => Auth::id()])->save();

            // Tăng số comment của story.
            $story->increment('comment_count');

            // Tăng số comment của chapter.
            if ($comment->chapter_id) {
                $comment->chapter->increment('comment_count');
            }

            return $comment;
        });

        /*
        |--------------------------------------------------------------------------
        | Load relationships
        |--------------------------------------------------------------------------
        */

        $comment->load([
            'user',
            'chapter',
            'replies.user',
        ]);

        // Gửi form thường (không AJAX) -> quay lại trang kèm flash message
        if (! $request->expectsJson()) {
            return back()->with('success', 'Đã gửi bình luận.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi bình luận.',
            'html' => view('components.comment', ['comment' => $comment])->render(),
        ]);
    }

    /**
     * Delete a comment.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $comment->user_id === Auth::id(),
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($comment) {

            $story = $comment->story;
            $chapter = $comment->chapter;

            if (! $comment->is_hidden) {
                // Reply chỉ có 1 cấp: xóa comment cha cũng gỡ số đếm của
                // các reply còn hiển thị của nó, nếu không comment_count
                // lệch dần (N-L3). Trừ có chặn ở 0 khi số đếm đã lệch
                // trước đó (NM-4).
                $decrement = 1 + $comment->replies()->where('is_hidden', false)->count();

                if ($story) {
                    StoryStatsService::decrementCommentCount('stories', $story->id, $decrement);
                }

                if ($chapter) {
                    StoryStatsService::decrementCommentCount('chapters', $chapter->id, $decrement);
                }
            }

            $comment->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bình luận.',
        ]);
    }
}
