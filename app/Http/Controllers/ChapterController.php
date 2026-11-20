<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ReadingHistory;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChapterController extends Controller
{
    /**
     * Permanently redirect legacy chapter-number URLs to a canonical slug URL.
     */
    public function legacy(Story $story, int $chapterNumber)
    {
        abort_unless($story->status_publish, 404);

        $chapter = $story->chapters()
            ->where('chapter_number', $chapterNumber)
            ->where('status', true)
            ->orderBy('id')
            ->firstOrFail();

        return redirect()->route('chapters.show', [$story, $chapter->slug], 301);
    }

    public function show(Story $story, Chapter $chapter)
    {
        abort_unless($story->status_publish && $chapter->status, 404);
        abort_unless($chapter->story_id === $story->id, 404);

        $this->trackView($story, $chapter);

        $prevChapter = Chapter::query()
            ->where('story_id', $story->id)
            ->where('status', true)
            ->where(function ($query) use ($chapter) {
                $query->where('chapter_number', '<', $chapter->chapter_number)
                    ->orWhere(function ($query) use ($chapter) {
                        $query->where('chapter_number', $chapter->chapter_number)
                            ->where('id', '<', $chapter->id);
                    });
            })
            ->orderByDesc('chapter_number')
            ->orderByDesc('id')
            ->first(['id', 'chapter_number', 'slug']);

        $nextChapter = Chapter::query()
            ->where('story_id', $story->id)
            ->where('status', true)
            ->where(function ($query) use ($chapter) {
                $query->where('chapter_number', '>', $chapter->chapter_number)
                    ->orWhere(function ($query) use ($chapter) {
                        $query->where('chapter_number', $chapter->chapter_number)
                            ->where('id', '>', $chapter->id);
                    });
            })
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->first(['id', 'chapter_number', 'slug']);

        if (Auth::check()) {
            $userId = (int) Auth::id();
            $storyId = (int) $story->id;
            $chapterId = (int) $chapter->id;

            // Read the current progress, then atomically upsert on the unique
            // (user_id, story_id) key. Concurrent first visits cannot create
            // duplicate rows or raise a unique-constraint exception.
            $historyQuery = DB::table((new ReadingHistory)->getTable())
                ->where('user_id', $userId)
                ->where('story_id', $storyId);

            $existing = $historyQuery->first(['chapter_id', 'scroll_percent']);
            $scrollPercent = $existing && (int) $existing->chapter_id === $chapterId
                ? (int) $existing->scroll_percent
                : 0;

            DB::table((new ReadingHistory)->getTable())->upsert(
                [[
                    'user_id' => $userId,
                    'story_id' => $storyId,
                    'chapter_id' => $chapterId,
                    'read_at' => now(),
                    'scroll_percent' => $scrollPercent,
                ]],
                ['user_id', 'story_id'],
                ['chapter_id', 'read_at', 'scroll_percent']
            );
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

        $throttleKey = 'chapter_view:'.request()->ip().':'.$chapter->id;

        // Cache::add() là check-and-set nguyên tử: hai view đồng thời chỉ
        // một lần đếm được, thay cho cặp has() rồi put() dễ đếm trùng.
        if (Cache::add($throttleKey, true, now()->addMinutes(30))) {
            $chapter->increment('views');
            $story->incrementViews();
        }

        $viewedInSession[] = $chapter->id;
        session(['viewed_chapters' => $viewedInSession]);
    }
}
