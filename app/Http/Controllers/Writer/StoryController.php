<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStoryRequest;
use App\Http\Requests\Admin\UpdateStoryRequest;
use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoryController extends Controller
{
    public function index()
    {
        $stories = auth()->user()->stories()
            ->withTrashed()
            ->withCount('chapters')
            ->latest('id')
            ->paginate(12);

        return view('writer.stories.index', compact('stories'));
    }

    public function create()
    {
        return view('writer.stories.create', [
            'categories' => Category::query()->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStoryRequest $request)
    {
        $data = $request->validated();
        $categoryIds = $data['categories'];
        $tagIds = $data['tags'] ?? [];
        unset($data['categories'], $data['tags']);

        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status_publish'] = true;
        $data['is_hot'] = $data['is_featured'] = $data['is_recommended'] = false;

        $cover = $request->file('cover_image')?->store('covers', 'public');
        if ($request->hasFile('cover_image') && ! $cover) {
            throw new \RuntimeException('Không thể lưu ảnh bìa.');
        }
        if ($cover) {
            $data['cover_image'] = $cover;
        }

        try {
            DB::transaction(function () use ($data, $categoryIds, $tagIds) {
                $story = auth()->user()->stories()->create($data);
                $story->categories()->sync($categoryIds);
                $story->tags()->sync($tagIds);
            });
        } catch (Throwable $exception) {
            if ($cover) {
                Storage::disk('public')->delete($cover);
            }
            throw $exception;
        }

        return redirect()->route('writer.stories.index')
            ->with('success', 'Đã đăng truyện. Truyện được công khai ngay; bạn hãy thêm chương đầu tiên.');
    }

    public function edit(Story $story)
    {
        $this->ensureOwner($story);

        return view('writer.stories.edit', [
            'story' => $story,
            'categories' => Category::query()->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'selectedCategoryIds' => $story->categories()->pluck('categories.id')->all(),
            'selectedTagIds' => $story->tags()->pluck('tags.id')->all(),
        ]);
    }

    public function update(UpdateStoryRequest $request, Story $story)
    {
        $this->ensureOwner($story);

        $data = $request->validated();
        $categoryIds = $data['categories'];
        $tagIds = $data['tags'] ?? [];
        unset($data['categories'], $data['tags'], $data['status_publish']);

        if ($data['title'] !== $story->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $story->id);
        }

        // Story submissions no longer require admin approval.
        $data['status_publish'] = true;

        $oldCover = $story->cover_image;
        $newCover = $request->file('cover_image')?->store('covers', 'public');
        if ($request->hasFile('cover_image') && ! $newCover) {
            throw new \RuntimeException('Không thể lưu ảnh bìa mới.');
        }
        if ($newCover) {
            $data['cover_image'] = $newCover;
        }

        try {
            DB::transaction(function () use ($story, $data, $categoryIds, $tagIds) {
                $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
                $lockedStory->update($data);
                $lockedStory->categories()->sync($categoryIds);
                $lockedStory->tags()->sync($tagIds);
            });
        } catch (Throwable $exception) {
            if ($newCover) {
                Storage::disk('public')->delete($newCover);
            }
            throw $exception;
        }

        if ($newCover && $oldCover && $oldCover !== $newCover) {
            Storage::disk('public')->delete($oldCover);
        }

        return redirect()->route('writer.stories.index')
            ->with('success', 'Đã cập nhật truyện thành công.');
    }

    public function destroy(Story $story)
    {
        $this->ensureOwner($story);
        $cover = $story->cover_image;

        DB::transaction(fn () => $story->delete());

        if ($cover) {
            Storage::disk('public')->delete($cover);
        }

        return redirect()->route('writer.stories.index')->with('success', 'Đã xóa truyện.');
    }

    private function ensureOwner(Story $story): void
    {
        abort_unless($story->user_id === auth()->id(), 403);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'truyen';
        $slug = $base;
        $suffix = 1;

        while (Story::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
