<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChapterRequest;
use App\Http\Requests\Admin\UpdateChapterRequest;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use App\Services\StoryStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChapterController extends Controller
{
    public function index(Story $story)
    {
        $chapters = $story->chapters()
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
        $title = $data['title'] ?? ('chuong-' . $data['chapter_number']);

        $chapter = DB::transaction(function () use ($story, $data, $stats, $title) {
            $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
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
        $story = $chapter->story;

        return view('admin.chapters.edit', compact('chapter', 'story'));
    }

    public function update(
        UpdateChapterRequest $request,
        Chapter $chapter,
        StoryStatsService $stats
    ) {
        $data = $request->validated();

        [$story, $chapter, $wasPublished] = DB::transaction(function () use ($chapter, $data, $stats) {
            $story = Story::query()->lockForUpdate()->findOrFail($chapter->story_id);
            $chapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);
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
            $story = Story::query()->lockForUpdate()->findOrFail($chapter->story_id);
            $chapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);
            $chapter->delete();
            $stats->refreshChapterStats($story);

            return $story;
        });

        return redirect()
            ->route('admin.stories.chapters.index', $story)
            ->with('success', 'Xóa chương thành công.');
    }

    private function notifyFollowers(Story $story, Chapter $chapter): void
    {
        User::query()
            ->whereHas('favorites', function ($query) use ($story) {
                $query->where('story_id', $story->id)
                    ->where('notify_new_chapter', true);
            })
            ->chunkById(200, function ($users) use ($chapter) {
                foreach ($users as $user) {
                    $user->notify(new NewChapterPublished($chapter));
                }
            });
    }

    private function generateUniqueSlug(string $title, int $storyId, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title) ?: 'chuong';

        if (ctype_digit($slug)) {
            $slug = 'chuong-'.$slug;
        }

        $original = $slug;
        $i = 1;

        while (
            Chapter::withTrashed()
                ->where('story_id', $storyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
