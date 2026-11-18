<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChapterRequest;
use App\Http\Requests\Admin\UpdateChapterRequest;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChapterController extends Controller
{
    public function index(Story $story)
    {
        $this->ensureOwner($story);
        $chapters = $story->chapters()->orderBy('chapter_number')->orderBy('id')->paginate(20);

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
        $data['slug'] = $this->uniqueSlug($title, $story->id);
        $data['status'] = false;

        DB::transaction(function () use ($story, $data, $stats) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
            $lockedStory->chapters()->create($data);
            $stats->refreshChapterStats($lockedStory);
        });

        return redirect()->route('writer.stories.chapters.index', $story)
            ->with('success', 'Đã lưu chương ở trạng thái nháp để quản trị viên duyệt.');
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
        $title = $data['title'] ?? 'chuong-'.$data['chapter_number'];

        if (Str::slug($title) !== $chapter->slug) {
            $data['slug'] = $this->uniqueSlug($title, $story->id, $chapter->id);
        }
        $data['status'] = false;

        DB::transaction(function () use ($chapter, $data, $story, $stats) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
            $lockedChapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);
            $lockedChapter->update($data);
            $stats->refreshChapterStats($lockedStory);
        });

        return redirect()->route('writer.stories.chapters.index', $story)
            ->with('success', 'Đã cập nhật chương. Chương được chuyển về trạng thái nháp để duyệt lại.');
    }

    public function destroy(Chapter $chapter, StoryStatsService $stats)
    {
        $story = $this->ownedStoryFor($chapter);

        DB::transaction(function () use ($chapter, $story, $stats) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
            $lockedChapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);
            $lockedChapter->delete();
            $stats->refreshChapterStats($lockedStory);
        });

        return redirect()->route('writer.stories.chapters.index', $story)->with('success', 'Đã xóa chương.');
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
        $slug = $base;
        $suffix = 1;

        while (Chapter::withTrashed()->where('story_id', $storyId)->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
