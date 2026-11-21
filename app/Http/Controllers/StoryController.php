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
        abort_unless($story->status_publish, 404);

        $story->load([
            'categories',
            'tags',
        ]);

        $publishedChapters = fn () => $story->chapters()->where('status', true);

        $chapters = $publishedChapters()
            ->select(['id', 'story_id', 'chapter_number', 'title', 'slug', 'views'])
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->paginate(100, pageName: 'chapter_page')
            ->withQueryString();

        $firstChapter = $publishedChapters()
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->first(['id', 'chapter_number', 'slug']);

        $latestChapter = $publishedChapters()
            ->orderByDesc('chapter_number')
            ->orderByDesc('id')
            ->first(['id', 'chapter_number', 'slug']);

        $comments = Comment::query()
            ->where('story_id', $story->id)
            ->whereNull('chapter_id')
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->with([
                'user',
                'chapter',
                'replies' => fn ($q) => $q
                    ->where('is_hidden', false)
                    // Giới hạn reply mỗi comment: comment gốc chỉ phân trang
                    // 10/trang nhưng reply load toàn bộ, một comment viral
                    // vài nghìn reply sẽ làm nặng DB và bộ nhớ (NM-3).
                    ->take(20)
                    ->with('user'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'comment_page')
            ->withQueryString();

        $userRating = Auth::check()
            ? $story->ratings()
                ->where('user_id', Auth::id())
                ->value('score')
            : null;

        $isFavorited = $story->isFavoritedBy(Auth::user());

        $categoryIds = $story->categories->modelKeys();
        $relatedStories = $categoryIds === []
            ? collect()
            : Story::published()
                ->whereKeyNot($story->id)
                ->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds))
                ->with('categories')
                ->orderByDesc('is_featured')
                ->orderByDesc('views')
                ->orderByDesc('id')
                ->limit(6)
                ->get();

        return view(
            'stories.show',
            compact(
                'story',
                'chapters',
                'firstChapter',
                'latestChapter',
                'comments',
                'userRating',
                'isFavorited',
                'relatedStories'
            )
        );
    }
}
