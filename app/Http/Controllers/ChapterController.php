<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ReadingHistory;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;

class ChapterController extends Controller
{
    public function show(Story $story, Chapter $chapter)
    {
        abort_unless($story->status_publish && $chapter->status, 404);
        abort_unless($chapter->story_id === $story->id, 404);

        $sessionKey = "viewed_chapter_{$chapter->id}";
        if (! session()->has($sessionKey)) {
            $chapter->increment('views');
            $story->incrementViews();
            session()->put($sessionKey, true);
        }

        $prevChapter = Chapter::where('story_id', $story->id)
            ->where('chapter_number', '<', $chapter->chapter_number)
            ->where('status', true)
            ->orderByDesc('chapter_number')
            ->first(['id', 'chapter_number']);

        $nextChapter = Chapter::where('story_id', $story->id)
            ->where('chapter_number', '>', $chapter->chapter_number)
            ->where('status', true)
            ->orderBy('chapter_number')
            ->first(['id', 'chapter_number']);

        if (Auth::check()) {
            ReadingHistory::updateOrCreate(
                ['user_id' => Auth::id(), 'story_id' => $story->id],
                ['chapter_id' => $chapter->id, 'read_at' => now()]
            );
        }

        $comments = $chapter->comments()
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->with(['user', 'replies' => fn ($q) => $q->where('is_hidden', false)->with('user')])
            ->latest()
            ->paginate(10);

        return view('chapters.show', compact('story', 'chapter', 'prevChapter', 'nextChapter', 'comments'));
    }
}