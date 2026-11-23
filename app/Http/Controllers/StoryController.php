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
            // One shared thread per story (story-level and every published chapter);
            // visible() is the same definition comment_count uses.
            ->visible()
            ->whereNull('parent_id')
            ->with(['user', 'chapter'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'comment_page')
            ->withQueryString();

        // Reply chỉ load tối đa 20 cho MỖI comment cha: take(20) trong
        // with() áp cho toàn bộ query eager-load nên comment cha đầu
        // tiên có 20+ reply sẽ chiếm hết quota, các cha sau hiện 0 reply
        // (P-M8). Tối đa 10 query nhỏ (mỗi cha một query) cho một trang.
        $comments->getCollection()->each(function (Comment $comment) {
            $comment->setRelation(
                'replies',
                $comment->replies()
                    ->where('is_hidden', false)
                    ->with('user')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->take(20)
                    ->get()
            );
        });

        $userRating = Auth::check()
            ? $story->ratings()
                ->where('user_id', Auth::id())
                ->value('score')
            : null;

        $isFavorited = $story->isFavoritedBy(Auth::user());

        $relatedStories = Story::relatedTo($story, 10);

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
