<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\ReadingHistory;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            ->with(['user', 'chapter'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Reply chỉ load tối đa 20 cho MỖI comment cha (xem P-M8 ở
        // StoryController@show).
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

        return view('chapters.show', compact(
            'story',
            'chapter',
            'prevChapter',
            'nextChapter',
            'comments'
        ));
    }

    /**
     * Ghi một lượt đọc thật cho chương: chỉ gọi từ JS ở trang đọc khi
     * người đọc đã ở lại trang đủ 15 giây VÀ cuộn qua 25% nội dung
     * chương (xem chapters/show.blade.php). Khách vãng lai cũng được
     * tính; bot không chạy JS nên không gọi được, nhưng vẫn chặn thêm
     * ở đây để phòng trường hợp gọi thẳng endpoint.
     *
     * Chống đếm trùng: mỗi user đã đăng nhập (hoặc mỗi IP với khách)
     * chỉ được tính 1 lượt đọc thật cho 1 chương trong 24 giờ.
     */
    public function recordRead(Story $story, Chapter $chapter): JsonResponse
    {
        abort_unless($story->status_publish && $chapter->status, 404);
        abort_unless($chapter->story_id === $story->id, 404);

        if ($this->isBotRequest()) {
            return response()->json(['status' => 'ignored']);
        }

        $key = Auth::check()
            ? 'chapter_read:user:'.Auth::id().':'.$chapter->id
            : 'chapter_read:ip:'.request()->ip().':'.$chapter->id;

        if (! Cache::add($key, true, now()->addDay())) {
            return response()->json(['status' => 'already_recorded']);
        }

        DB::transaction(function () use ($chapter, $story) {
            $chapter->timestamps = false;
            $chapter->increment('reads_count');
            $chapter->timestamps = true;

            $story->timestamps = false;
            $story->increment('reads_count');
            $story->timestamps = true;
        });

        return response()->json(['status' => 'ok']);
    }

    /**
     * Bot/crawler/traffic tự động: không tính view hay lượt đọc thật.
     * Chỉ kiểm tra user-agent nên không phải lớp bảo vệ tuyệt đối,
     * nhưng đủ lọc Googlebot/Bingbot/Facebook... vốn không chạy JS.
     */
    private function isBotRequest(): bool
    {
        $agent = Str::lower((string) request()->userAgent());

        if ($agent === '') {
            return false;
        }

        return Str::contains($agent, [
            'bot',
            'spider',
            'crawler',
            'slurp',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'pinterest',
            'embedly',
            'headless',
            'curl',
            'wget',
            'python-requests',
            'go-http-client',
            'httpclient',
        ]);
    }

    private function trackView(Story $story, Chapter $chapter): void
    {
        // Bot không tính view (xem isBotRequest()).
        if ($this->isBotRequest()) {
            return;
        }

        $viewedInSession = session('viewed_chapters', []);

        if (in_array($chapter->id, $viewedInSession, true)) {
            return;
        }

        // Throttle theo user đã đăng nhập, chỉ dùng IP cho khách vãng lai:
        // nhiều user chung 1 IP (wifi công ty/trường, CGNAT) trước đây chỉ
        // người đầu tiên trong 30 phút được tính view.
        $throttleKey = Auth::check()
            ? 'chapter_view:user:'.Auth::id().':'.$chapter->id
            : 'chapter_view:ip:'.request()->ip().':'.$chapter->id;

        // Cache::add() là check-and-set nguyên tử: hai view đồng thời chỉ
        // một lần đếm được, thay cho cặp has() rồi put() dễ đếm trùng.
        if (Cache::add($throttleKey, true, now()->addMinutes(30))) {
            // Hai lần tăng (chương + truyện) phải chung một transaction,
            // nếu không câu thứ hai lỗi sẽ làm hai counter lệch nhau vĩnh
            // viễn. Đồng thời đóng băng updated_at: tăng view không phải
            // cập nhật nội dung nên không được làm sitemap tưởng truyện
            // mới cập nhật (xem StoryStatsService, NL-4).
            DB::transaction(function () use ($chapter, $story) {
                $chapter->timestamps = false;
                $chapter->increment('views');
                $chapter->timestamps = true;

                $story->timestamps = false;
                $story->incrementViews();
                $story->timestamps = true;
            });
        }

        $viewedInSession[] = $chapter->id;

        // Giới hạn 100 chương gần nhất: session không phình vô hạn, và
        // chương cũ rơi khỏi danh sách sẽ được đếm lại sau khi hết 30 phút
        // throttle (trước đây session nhớ mãi nên throttle kéo dài vô
        // hạn) (N-L4).
        $viewedInSession = array_slice(array_values(array_unique($viewedInSession)), -100);

        session(['viewed_chapters' => $viewedInSession]);
    }
}
