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

    /**
     * Đếm view an toàn, chống spam bằng 2 lớp:
     * 1. Session: 1 mảng duy nhất chứa id các chapter đã xem trong phiên,
     *    thay vì tạo 1 key riêng cho mỗi chapter (tránh session phình to).
     * 2. Cache theo IP + chapter: chặn refresh liên tục / bypass session
     *    (gọi trực tiếp không qua trình duyệt, incognito lặp lại, v.v).
     */
    private function trackView(Story $story, Chapter $chapter): void
    {
        $viewedInSession = session('viewed_chapters', []);

        if (in_array($chapter->id, $viewedInSession, true)) {
            return;
        }

        $throttleKey = 'chapter_view:' . request()->ip() . ':' . $chapter->id;

        // Nếu trong 30 phút gần đây IP này đã tính view cho chapter này rồi thì bỏ qua,
        // kể cả khi session bị xóa / request không mang cookie.
        if (Cache::has($throttleKey)) {
            $viewedInSession[] = $chapter->id;
            session(['viewed_chapters' => $viewedInSession]);
            return;
        }

        $chapter->increment('views');
        $story->incrementViews();

        Cache::put($throttleKey, true, now()->addMinutes(30));

        $viewedInSession[] = $chapter->id;
        session(['viewed_chapters' => $viewedInSession]);
    }
}