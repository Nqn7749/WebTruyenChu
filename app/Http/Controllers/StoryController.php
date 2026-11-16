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

        $story->load([
            'categories',
            'tags',
        ]);

        // Query gốc cho các chương đã xuất bản.
        $publishedChapters = fn () => $story->chapters()->where('status', true);

        // Danh sách chương (phân trang riêng bằng "chapter_page").
        $chapters = $publishedChapters()
            ->orderBy('chapter_number')
            ->paginate(100, ['*'], 'chapter_page')
            ->withQueryString();

        // Chương đầu / chương mới nhất của TOÀN BỘ truyện, không phụ thuộc trang đang xem.
        $firstChapter = $publishedChapters()
            ->orderBy('chapter_number')
            ->first(['id', 'chapter_number']);

        $latestChapter = $publishedChapters()
            ->orderByDesc('chapter_number')
            ->first(['id', 'chapter_number']);

        // Bình luận (phân trang riêng bằng "comment_page").
        $comments = Comment::query()
            ->where('story_id', $story->id)
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->with([
                'user',
                'chapter',
                'replies' => fn ($q) => $q
                    ->where('is_hidden', false)
                    ->with('user'),
            ])
            ->latest()
            ->paginate(10, ['*'], 'comment_page')
            ->withQueryString();

        // Rating của user hiện tại.
        $userRating = Auth::check()
            ? $story->ratings()
                ->where('user_id', Auth::id())
                ->value('score')
            : null;

        $isFavorited = $story->isFavoritedBy(Auth::user());

        return view(
            'stories.show',
            compact(
                'story',
                'chapters',
                'firstChapter',
                'latestChapter',
                'comments',
                'userRating',
                'isFavorited'
            )
        );
    }
}