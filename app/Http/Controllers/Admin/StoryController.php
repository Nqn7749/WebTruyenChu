<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStoryRequest;
use App\Http\Requests\Admin\UpdateStoryRequest;
use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function index()
    {
        $stories = Story::with('user')
            ->latest()
            ->paginate(15);

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

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        $story = Story::create($data);
        $story->categories()->sync($request->input('categories'));
        $story->tags()->sync($request->input('tags', []));

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

        if ($request->hasFile('cover_image')) {
            if ($story->cover_image) {
                Storage::disk('public')->delete($story->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        $story->update($data);
        $story->categories()->sync($request->input('categories'));
        $story->tags()->sync($request->input('tags', []));

        return redirect()->route('admin.stories.index')
            ->with('success', 'Cập nhật truyện thành công.');
    }

    public function destroy(Story $story)
    {
        if ($story->cover_image) {
            Storage::disk('public')->delete($story->cover_image);
        }

        $story->delete();

        return redirect()->route('admin.stories.index')
            ->with('success', 'Xóa truyện thành công.');
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while (
            Story::withTrashed()
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