<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class StoryController extends Controller
{
    public function show(Story $story)
    {
        abort_unless($story->status_publish, 404);

        $story->load(['categories', 'tags', 'user']);

        $chapters = $story->chapters()
            ->where('status', true)
            ->orderBy('chapter_number')
            ->get(['id', 'story_id', 'chapter_number', 'title', 'views']);

        $comments = $story->comments()
            ->whereNull('parent_id')
            ->whereNull('chapter_id')
            ->where('is_hidden', false)
            ->with(['user', 'replies' => fn ($q) => $q->where('is_hidden', false)->with('user')])
            ->latest()
            ->paginate(10);

        $userRating = Auth::check()
            ? $story->ratings()->where('user_id', Auth::id())->value('score')
            : null;

        $isFavorited = $story->isFavoritedBy(Auth::user());

        return view('stories.show', compact('story', 'chapters', 'comments', 'userRating', 'isFavorited'));
    }
}