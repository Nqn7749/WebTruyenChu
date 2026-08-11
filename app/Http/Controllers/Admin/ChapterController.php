<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChapterRequest;
use App\Http\Requests\Admin\UpdateChapterRequest;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function index(Story $story)
    {
        $chapters = $story->chapters()
            ->orderBy('chapter_number')
            ->paginate(20);

        return view('admin.chapters.index', compact('story', 'chapters'));
    }

    public function create(Story $story)
    {
        $nextNumber = ($story->chapters()->max('chapter_number') ?? 0) + 1;

        return view('admin.chapters.create', compact('story', 'nextNumber'));
    }

    public function store(StoreChapterRequest $request, Story $story)
    {
        $data = $request->validated();
        $data['story_id'] = $story->id;
        $data['slug'] = $this->generateUniqueSlug(
            $data['title'] ?? ('chuong-' . $data['chapter_number']),
            $story->id
        );

        Chapter::create($data);

        $story->increment('chapter_count');
        $story->forceFill(['last_chapter_at' => now()])->save();

        return redirect()->route('admin.stories.chapters.index', $story)
            ->with('success', 'Tạo chương thành công.');
    }

    public function edit(Chapter $chapter)
    {
        $story = $chapter->story;

        return view('admin.chapters.edit', compact('chapter', 'story'));
    }

    public function update(UpdateChapterRequest $request, Chapter $chapter)
    {
        $data = $request->validated();
        $expectedTitle = $data['title'] ?? ('chuong-' . $data['chapter_number']);
        if (Str::slug($expectedTitle) !== $chapter->slug) {
            $data['slug'] = $this->generateUniqueSlug($expectedTitle, $chapter->story_id, $chapter->id);
        }

        $chapter->update($data);
        
        $chapter->story->forceFill(['last_chapter_at' => now()])->save();

        return redirect()->route('admin.stories.chapters.index', $chapter->story)
            ->with('success', 'Cập nhật chương thành công.');
    }

    public function destroy(Chapter $chapter)
    {
        $story = $chapter->story;
        $chapter->delete();

        $story->decrement('chapter_count');

        return redirect()->route('admin.stories.chapters.index', $story)
            ->with('success', 'Xóa chương thành công.');
    }

    private function generateUniqueSlug(string $title, int $storyId, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while (
            Chapter::withTrashed()
                ->where('story_id', $storyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}