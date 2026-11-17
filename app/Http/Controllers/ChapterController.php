<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ReadingHistory;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ChapterController extends Controller
{
    public function show(Story $story, Chapter $chapter)
    {
        abort_unless($story->status_publish && $chapter->status, 404);
        abort_unless($chapter->story_id === $story->id, 404);

        $this->trackView($story, $chapter);

        $prevChapter = Chapter::where('story_id', $story->id)
            ->where('chapter_number', '<', $chapter->chapter_number)
            ->where('status', true)
            ->orderByDesc('chapter_number')
            ->orderByDesc('id')
            ->first(['id', 'chapter_number']);

        $nextChapter = Chapter::where('story_id', $story->id)
            ->where('chapter_number', '>', $chapter->chapter_number)
            ->where('status', true)
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->first(['id', 'chapter_number']);

        if (Auth::check()) {
            $history = ReadingHistory::firstOrNew([
                'user_id' => Auth::id(),
                'story_id' => $story->id,
            ]);

            if ($history->chapter_id !== $chapter->id) {
                $history->scroll_percent = 0;
            }

            $history->chapter_id = $chapter->id;
            $history->read_at = now();
            $history->save();
        }

        $comments = $story->comments()
            ->where('chapter_id', $chapter->id)
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
            ->paginate(10)
            ->withQueryString();

        return view('chapters.show', compact(
            'story',
            'chapter',
            'prevChapter',
            'nextChapter',
            'comments'
        ));
    }

    private function trackView(Story $story, Chapter $chapter): void
    {
        $viewedInSession = session('viewed_chapters', []);

        if (in_array($chapter->id, $viewedInSession, true)) {
            return;
        }

        $throttleKey = 'chapter_view:' . request()->ip() . ':' . $chapter->id;

        if (Cache::has($throttleKey)) {
            $viewedInSession[] = $chapter->id;
            session(['viewed_chapters' => $viewedInSession]);
            return;
        }

        $chapter->increment('views');
        $story->incrementViews();

        Cache::put(
            $throttleKey,
            true,
            now()->addMinutes(30)
        );

        $viewedInSession[] = $chapter->id;
        session(['viewed_chapters' => $viewedInSession]);
    }
}
