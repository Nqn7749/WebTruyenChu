<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class StoryController extends Controller
{
    /**
     * Display the story detail page.
     */
    public function show(Story $story)
    {
        // Chỉ cho phép xem truyện đã được xuất bản.
        abort_unless($story->status_publish, 404);

        // Load thông tin liên quan đến truyện.
        $story->load([
            'categories',
            'tags',
            'user',
        ]);

        // Danh sách chương đã xuất bản.
        $chapters = $story->chapters()
            ->where('status', true)
            ->orderBy('chapter_number')
            ->paginate(100);

        // Bình luận trực tiếp của truyện.
        // Không lấy bình luận thuộc chapter.
        // Không lấy reply ở cấp cao nhất.
        $comments = Comment::query()
            ->where('story_id', $story->id)
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->with([
                'user',
                'chapter',
                'replies.user',
            ])
            ->latest()
            ->paginate(10);

        // Rating của user hiện tại.
        $userRating = Auth::check()
            ? $story->ratings()
                ->where('user_id', Auth::id())
                ->value('score')
            : null;

        // Kiểm tra user đã yêu thích truyện chưa.
        $isFavorited = $story->isFavoritedBy(Auth::user());

        return view(
            'stories.show',
            compact(
                'story',
                'chapters',
                'comments',
                'userRating',
                'isFavorited'
            )
        );
    }
}