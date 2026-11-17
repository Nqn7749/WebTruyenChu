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
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->paginate(100, ['*'], 'chapter_page')
            ->withQueryString();

        $firstChapter = $publishedChapters()
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->first(['id', 'chapter_number']);

        $latestChapter = $publishedChapters()
            ->orderByDesc('chapter_number')
            ->orderByDesc('id')
            ->first(['id', 'chapter_number']);

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
