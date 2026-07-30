<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTagRequest;
use App\Http\Requests\Admin\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::withCount('stories')->latest()->paginate(20);

        return view('admin.tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.tags.create');
    }

    public function store(StoreTagRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->generateUniqueSlug($data['name']);

        Tag::create($data);

        return redirect()->route('admin.tags.index')
            ->with('success', 'Tạo thẻ tag thành công.');
    }

    public function edit(Tag $tag)
    {
        return view('admin.tags.edit', compact('tag'));
    }

    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $data = $request->validated();

        if ($data['name'] !== $tag->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $tag->id);
        }

        $tag->update($data);

        return redirect()->route('admin.tags.index')
            ->with('success', 'Cập nhật thẻ tag thành công.');
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return redirect()->route('admin.tags.index')
            ->with('success', 'Xóa thẻ tag thành công.');
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (
            Tag::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}