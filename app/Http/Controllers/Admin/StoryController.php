<?php

namespace App\Http\Controllers\Admin;

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
        $stories = Story::with('user')->latest()->paginate(15);

        return view('admin.stories.index', compact('stories'));
    }

    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();

        return view('admin.stories.create', compact('categories', 'tags'));
    }

    public function store(StoreStoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->generateUniqueSlug($data['title']);
        $data['user_id'] = auth()->id();

        $newCover = $request->file('cover_image')?->store('covers', 'public');

        if ($newCover) {
            $data['cover_image'] = $newCover;
        }

        try {
            DB::transaction(function () use ($data, $request) {
                $story = Story::create($data);
                $story->categories()->sync($request->input('categories', []));
                $story->tags()->sync($request->input('tags', []));
            });
        } catch (Throwable $exception) {
            if ($newCover) {
                Storage::disk('public')->delete($newCover);
            }

            throw $exception;
        }

        return redirect()->route('admin.stories.index')
            ->with('success', 'Tạo truyện thành công.');
    }

    public function edit(Story $story)
    {
        $categories = Category::all();
        $tags = Tag::all();
        $selectedCategoryIds = $story->categories()->pluck('categories.id')->toArray();
        $selectedTagIds = $story->tags()->pluck('tags.id')->toArray();

        return view('admin.stories.edit', compact(
            'story',
            'categories',
            'tags',
            'selectedCategoryIds',
            'selectedTagIds'
        ));
    }

    public function update(UpdateStoryRequest $request, Story $story)
    {
        $data = $request->validated();

        if ($data['title'] !== $story->title) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $story->id);
        }

        $oldCover = $story->cover_image;
        $newCover = $request->file('cover_image')?->store('covers', 'public');

        if ($newCover) {
            $data['cover_image'] = $newCover;
        }

        try {
            DB::transaction(function () use ($story, $data, $request) {
                $story->update($data);
                $story->categories()->sync($request->input('categories', []));
                $story->tags()->sync($request->input('tags', []));
            });
        } catch (Throwable $exception) {
            if ($newCover) {
                Storage::disk('public')->delete($newCover);
            }

            throw $exception;
        }

        // Keep the old cover until the new file and database changes succeed.
        if ($newCover && $oldCover && $oldCover !== $newCover) {
            Storage::disk('public')->delete($oldCover);
        }

        return redirect()->route('admin.stories.index')
            ->with('success', 'Cập nhật truyện thành công.');
    }

    public function destroy(Story $story)
    {
        $cover = $story->cover_image;

        DB::transaction(fn () => $story->delete());

        if ($cover) {
            Storage::disk('public')->delete($cover);
        }

        return redirect()->route('admin.stories.index')
            ->with('success', 'Xóa truyện thành công.');
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title) ?: 'truyen';
        $original = $slug;
        $i = 1;

        while (
            Story::withTrashed()
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
