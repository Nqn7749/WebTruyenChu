<?php

namespace App\Http\Controllers\Writer;

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
        $this->ensureOwner($story);
        $chapters = $story->chapters()
            ->select(['id', 'story_id', 'chapter_number', 'title', 'slug', 'status'])
            ->orderBy('chapter_number')
            ->orderBy('id')
            ->paginate(20);

        return view('writer.chapters.index', compact('story', 'chapters'));
    }

    public function create(Story $story)
    {
        $this->ensureOwner($story);
        $chapterNumber = ($story->chapters()->max('chapter_number') ?? 0) + 1;

        return view('writer.chapters.create', compact('story', 'chapterNumber'));
    }

    public function store(StoreChapterRequest $request, Story $story, StoryStatsService $stats)
    {
        $this->ensureOwner($story);
        $data = $request->validated();
        $title = $data['title'] ?? 'chuong-'.$data['chapter_number'];
        $data['status'] = $request->boolean('status');

        $chapter = DB::transaction(function () use ($story, $data, $stats, $title) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
            $this->assertChapterNumberAvailable($lockedStory, (int) $data['chapter_number']);
            $data['slug'] = $this->uniqueSlug($title, $lockedStory->id);
            $chapter = $lockedStory->chapters()->create($data);
            $stats->refreshChapterStats($lockedStory);

            return $chapter;
        });

        if ($chapter->status) {
            $this->notifyFollowers($story, $chapter);
        }

        return redirect()->route('writer.stories.chapters.index', $story)
            ->with('success', $chapter->status
                ? 'Đã đăng chương công khai thành công.'
                : 'Đã lưu chương ở trạng thái nháp.');
    }

    public function preview(Chapter $chapter)
    {
        $story = $this->ownedStoryFor($chapter);

        return view('writer.chapters.preview', compact('story', 'chapter'));
    }

    public function edit(Chapter $chapter)
    {
        $story = $this->ownedStoryFor($chapter);

        return view('writer.chapters.edit', compact('story', 'chapter'));
    }

    public function update(UpdateChapterRequest $request, Chapter $chapter, StoryStatsService $stats)
    {
        $story = $this->ownedStoryFor($chapter);
        $data = $request->validated();
        $data['status'] = $request->boolean('status');

        [$story, $chapter, $wasPublished] = DB::transaction(function () use ($chapter, $data, $story, $stats) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
            $lockedChapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);
            $this->assertChapterNumberAvailable($lockedStory, (int) $data['chapter_number'], $lockedChapter->id);

            // Keep the original slug stable when a writer edits the chapter title.

            $wasPublished = (bool) $lockedChapter->status;
            $lockedChapter->update($data);
            $stats->refreshChapterStats($lockedStory);

            // Gỡ/đăng lại công khai làm comment của chương hiện/ẩn theo
            // scopeVisible nên phải đếm lại comment_count của truyện:
            // đếm lại từ source chữa được cả hai chiều và cả số đếm
            // đã lệch từ trước (R7). Chỉ chạy khi trạng thái thật sự
            // đổi. refreshStories chạy trong cùng transaction này nên
            // lockForUpdate trên truyện đã giữ là an toàn.
            if ($wasPublished !== (bool) $lockedChapter->status) {
                $stats->refreshStories([$lockedStory->id]);
            }

            return [$lockedStory, $lockedChapter, $wasPublished];
        });

        if (! $wasPublished && $chapter->status) {
            $this->notifyFollowers($story, $chapter);
        }

        return redirect()->route('writer.stories.chapters.index', $story)
            ->with('success', $chapter->status
                ? 'Đã lưu và đăng chương công khai thành công.'
                : 'Đã lưu chương ở trạng thái nháp.');
    }

    public function destroy(Chapter $chapter, StoryStatsService $stats)
    {
        $story = $this->ownedStoryFor($chapter);

        DB::transaction(function () use ($chapter, $story, $stats) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
            $lockedChapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);

            // Comment của chương này không còn xem được ở đâu sau khi
            // xóa mềm chương nên phải trừ khỏi comment_count của truyện
            // (C-M2); đếm lại qua refreshStories cũng loại chúng, đây
            // là lưới an toàn.
            $visibleComments = $lockedChapter->comments()->visible()->count();

            $lockedChapter->delete();

            if ($visibleComments > 0) {
                StoryStatsService::decrementCommentCount('stories', $lockedStory->id, $visibleComments);
            }

            $stats->refreshChapterStats($lockedStory);
        });

        return redirect()->route('writer.stories.chapters.index', $story)->with('success', 'Đã xóa chương.');
    }

    private function notifyFollowers(Story $story, Chapter $chapter): void
    {
        if (! $story->status_publish || ! $chapter->status) {
            return;
        }

        SendNewChapterNotifications::dispatch($chapter->id);
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

    private function ownedStoryFor(Chapter $chapter): Story
    {
        $story = $chapter->story;

        abort_if(! $story, 404);
        $this->ensureOwner($story);

        return $story;
    }

    private function ensureOwner(Story $story): void
    {
        abort_unless($story->user_id === auth()->id(), 403);
    }

    private function uniqueSlug(string $title, int $storyId, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'chuong';

        if (ctype_digit($base)) {
            $base = 'chuong-'.$base;
        }

        // Cột slug là varchar(255): cắt base còn tối đa 250 ký tự rồi
        // mới thêm hậu tố trùng, để slug cuối cùng không bao giờ vượt
        // 255 ký tự rồi lỗi 22001 (V3).
        $base = mb_substr($base, 0, 250);

        $slug = $base;
        $suffix = 1;

        while (Chapter::withTrashed()->where('story_id', $storyId)->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $suffixText = '-'.$suffix++;
            $slug = mb_substr($base, 0, 255 - mb_strlen($suffixText)).$suffixText;
        }

        return $slug;
    }
}
