<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChapterRequest;
use App\Http\Requests\Admin\UpdateChapterRequest;
use App\Jobs\SendNewChapterNotifications;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChapterController extends Controller
{
    public function index(Story $story)
    {
        $chapters = $story->chapters()
            ->select(['id', 'story_id', 'chapter_number', 'title', 'slug', 'views', 'status'])
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->paginate(20);

        return view('admin.chapters.index', compact('story', 'chapters'));
    }

    public function create(Story $story)
    {
        $nextNumber = ($story->chapters()->max('chapter_number') ?? 0) + 1;

        return view('admin.chapters.create', compact('story', 'nextNumber'));
    }

    public function store(StoreChapterRequest $request, Story $story, StoryStatsService $stats)
    {
        $data = $request->validated();
        $title = $data['title'] ?? ('chuong-'.$data['chapter_number']);

        $chapter = DB::transaction(function () use ($story, $data, $stats, $title) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
            $this->assertChapterNumberAvailable($lockedStory, (int) $data['chapter_number']);
            $data['slug'] = $this->generateUniqueSlug($title, $lockedStory->id);
            $chapter = $lockedStory->chapters()->create($data);
            $stats->refreshChapterStats($lockedStory);

            return $chapter;
        });

        if ($chapter->status) {
            $this->notifyFollowers($story, $chapter);
        }

        return redirect()
            ->route('admin.stories.chapters.index', $story)
            ->with('success', 'Tạo chương thành công.');
    }

    public function edit(Chapter $chapter)
    {
        // Story có thể đã soft-delete: belongsTo mặc định loại truyện đã
        // xóa nên $chapter->story trả null rồi view 500 (N-L1).
        $story = $chapter->story()->withTrashed()->firstOrFail();

        return view('admin.chapters.edit', compact('chapter', 'story'));
    }

    public function update(
        UpdateChapterRequest $request,
        Chapter $chapter,
        StoryStatsService $stats
    ) {
        $data = $request->validated();

        [$story, $chapter, $wasPublished] = DB::transaction(function () use ($chapter, $data, $stats) {
            $story = Story::query()->withTrashed()->lockForUpdate()->findOrFail($chapter->story_id);
            $chapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);
            $this->assertChapterNumberAvailable($story, (int) $data['chapter_number'], $chapter->id);
            $wasPublished = (bool) $chapter->status;
            // Keep the original slug stable when an administrator edits the title.
            $chapter->update($data);
            $stats->refreshChapterStats($story);

            return [$story, $chapter, $wasPublished];
        });

        if (! $wasPublished && $chapter->status) {
            $this->notifyFollowers($story, $chapter);
        }

        return redirect()
            ->route('admin.stories.chapters.index', $story)
            ->with('success', 'Cập nhật chương thành công.');
    }

    public function destroy(Chapter $chapter, StoryStatsService $stats)
    {
        $story = DB::transaction(function () use ($chapter, $stats) {
            $story = Story::query()->withTrashed()->lockForUpdate()->findOrFail($chapter->story_id);
            $chapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);

            // Comment của chương này không còn xem được ở đâu sau khi
            // xóa mềm chương nên phải trừ khỏi comment_count của truyện
            // (C-M2); đếm lại qua refreshStories cũng loại chúng, đây
            // là lưới an toàn.
            $visibleComments = $chapter->comments()->visible()->count();

            $chapter->delete();

            if ($visibleComments > 0) {
                StoryStatsService::decrementCommentCount('stories', $story->id, $visibleComments);
            }

            $stats->refreshChapterStats($story);

            return $story;
        });

        return redirect()
            ->route('admin.stories.chapters.index', $story)
            ->with('success', 'Xóa chương thành công.');
    }

    /**
     * Xóa vĩnh viễn chương đã xóa mềm (F11): ChapterObserver::forceDeleted
     * dọn notification của chương. Route model binding loại chương đã
     * xóa mềm nên phải tra bằng withTrashed() thủ công. Chưa có UI: route
     * đã có sẵn để nối nút xóa vĩnh viễn sau.
     */
    public function forceDestroy(int $story, int $chapter, StoryStatsService $stats)
    {
        $ownerStory = Story::query()->withTrashed()->findOrFail($story);
        $trashedChapter = Chapter::query()->withTrashed()->findOrFail($chapter);
        abort_unless($trashedChapter->story_id === $ownerStory->id, 404);

        DB::transaction(function () use ($trashedChapter, $ownerStory, $stats) {
            $chapter = Chapter::query()->withTrashed()->lockForUpdate()->findOrFail($trashedChapter->id);
            $chapter->forceDelete();
            $stats->refreshChapterStats($ownerStory);
        });

        return redirect()
            ->route('admin.stories.chapters.index', $ownerStory)
            ->with('success', 'Đã xóa vĩnh viễn chương.');
    }

    private function assertChapterNumberAvailable(Story $story, int $number, ?int $ignoreChapterId = null): void
    {
        $exists = $story->chapters()
            ->where('chapter_number', $number)
            ->when($ignoreChapterId, fn ($query) => $query->where('chapters.id', '!=', $ignoreChapterId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'chapter_number' => 'Số chương này đã được sử dụng trong truyện.',
            ]);
        }
    }

    private function notifyFollowers(Story $story, Chapter $chapter): void
    {
        if (! $story->status_publish || ! $chapter->status) {
            return;
        }

        SendNewChapterNotifications::dispatch($chapter->id);
    }

    private function generateUniqueSlug(string $title, int $storyId, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title) ?: 'chuong';

        if (ctype_digit($slug)) {
            $slug = 'chuong-'.$slug;
        }

        // Cột slug là varchar(255): cắt base còn tối đa 250 ký tự rồi
        // mới thêm hậu tố trùng, để slug cuối cùng không bao giờ vượt
        // 255 ký tự rồi lỗi 22001 (V3).
        $original = mb_substr($slug, 0, 250);
        $slug = $original;
        $i = 1;

        while (
            Chapter::withTrashed()
                ->where('story_id', $storyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffixText = "-{$i}";
            $i++;
            $slug = mb_substr($original, 0, 255 - mb_strlen($suffixText)).$suffixText;
        }

        return $slug;
    }
}
