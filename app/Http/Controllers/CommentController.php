<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
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
    ): JsonResponse {
        $data = $request->validated();

        $data['user_id'] = Auth::id();
        $data['story_id'] = $story->id;

        /*
        |--------------------------------------------------------------------------
        | Validate chapter
        |--------------------------------------------------------------------------
        */

        if (!empty($data['chapter_id'])) {
            $chapter = Chapter::findOrFail($data['chapter_id']);

            abort_unless(
                $chapter->story_id === $story->id,
                404
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate parent comment
        |--------------------------------------------------------------------------
        */

        if (!empty($data['parent_id'])) {
            $parent = Comment::findOrFail($data['parent_id']);

            abort_unless(
                $parent->story_id === $story->id,
                404
            );

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

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi bình luận.',
            'html' => view('components.comment', [
                'comment' => $comment,
            ])->render(),
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

            if (!$comment->is_hidden) {

                if ($story) {
                    $story->decrement('comment_count');
                }

                if ($chapter) {
                    $chapter->decrement('comment_count');
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