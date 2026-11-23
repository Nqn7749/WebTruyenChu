<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStoryRequest;
use App\Http\Requests\Admin\UpdateStoryRequest;
use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use App\Services\HomeCacheService;
use App\Services\StoryDeletionService;
use Illuminate\Database\QueryException;
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
            'popularTags' => $this->popularTags(),
        ]);
    }

    public function store(StoreStoryRequest $request)
    {
        $data = $request->validated();
        $categoryIds = $data['categories'];
        $tagInput = $data['tags'] ?? '';
        unset($data['categories'], $data['tags']);

        $data['slug'] = $this->uniqueSlug($data['title']);

        $cover = $request->file('cover_image')?->store('covers', 'public');
        if ($request->hasFile('cover_image') && ! $cover) {
            throw new \RuntimeException('Không thể lưu ảnh bìa.');
        }
        if ($cover) {
            $data['cover_image'] = $cover;
        }

        try {
            // Tác giả tự đăng: truyện mới được công khai ngay, không cần admin
            // duyệt. Các cờ hệ thống (status_publish, is_hot, ...) không nằm
            // trong $fillable nên gán riêng bằng forceFill() sau khi tạo.
            // Admin vẫn có thể gỡ công khai sau (Admin\StoryController::togglePublish).
            // Thử lại tối đa 3 lần nếu hai người đăng cùng tiêu đề một lúc
            // khiến slug bị trùng (lỗi unique 23000) — xem uniqueSlug().
            $attempts = 0;

            do {
                $attempts++;

                try {
                    DB::transaction(function () use ($data, $categoryIds, $tagInput) {
                        $story = auth()->user()->stories()->create($data);
                        $story->forceFill([
                            'status_publish' => true,
                            'is_hot' => false,
                            'is_featured' => false,
                            'is_recommended' => false,
                        ])->save();
                        $story->categories()->sync($categoryIds);
                        $story->tags()->sync(Tag::idsFromInput($tagInput));

                        // sync() pivot không kích hoạt observer của Story
                        // nên cache trang chủ không tự làm mới: đổi chỉ
                        // thể loại/tag cũng phải invalidate tay (W1).
                        app(HomeCacheService::class)->invalidate();
                    });

                    break;
                } catch (QueryException $exception) {
                    if ($attempts >= 3 || $exception->getCode() !== '23000') {
                        throw $exception;
                    }

                    // Slug vừa bị người khác chiếm trước, tạo slug mới rồi thử lại.
                    // Hậu tố ngẫu nhiên dài 7 ký tự nên cắt slug còn tối
                    // đa 248 ký tự để tổng không vượt 255 (V3).
                    $data['slug'] = mb_substr($this->uniqueSlug($data['title']), 0, 248).'-'.Str::lower(Str::random(6));
                }
            } while ($attempts < 3);
        } catch (Throwable $exception) {
            if ($cover) {
                Storage::disk('public')->delete($cover);
            }
            throw $exception;
        }

        return redirect()->route('writer.stories.index')
            ->with('success', 'Đã đăng truyện. Hãy thêm chương đầu tiên trong vòng 30 ngày.');
    }

    public function edit(Story $story)
    {
        $this->ensureOwner($story);

        return view('writer.stories.edit', [
            'story' => $story,
            'categories' => Category::query()->orderBy('name')->get(),
            'popularTags' => $this->popularTags(),
            'selectedCategoryIds' => $story->categories()->pluck('categories.id')->all(),
            'tagNames' => $story->tags()->orderBy('name')->pluck('tags.name')->implode(', '),
        ]);
    }

    /**
     * Most used tags, offered as one-click suggestions next to the free-text field.
     */
    private function popularTags()
    {
        return Tag::query()
            ->withCount('stories')
            ->orderByDesc('stories_count')
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name']);
    }

    public function update(UpdateStoryRequest $request, Story $story)
    {
        $this->ensureOwner($story);

        $data = $request->validated();
        $categoryIds = $data['categories'];
        $tagInput = $data['tags'] ?? '';
        unset($data['categories'], $data['tags'], $data['status_publish']);

        // Preserve the public slug when a title changes so existing links and search indexes remain valid.
        // Không đụng tới status_publish khi sửa: truyện công khai thì vẫn
        // công khai, truyện đã bị gỡ thì vẫn bị gỡ. Chỉ admin mới được
        // công khai/gỡ công khai (Admin\StoryController::togglePublish).

        $oldCover = $story->cover_image;
        $newCover = $request->file('cover_image')?->store('covers', 'public');
        if ($request->hasFile('cover_image') && ! $newCover) {
            throw new \RuntimeException('Không thể lưu ảnh bìa mới.');
        }
        if ($newCover) {
            $data['cover_image'] = $newCover;
        }

        try {
            DB::transaction(function () use ($story, $data, $categoryIds, $tagInput) {
                $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
                $lockedStory->update($data);
                $lockedStory->categories()->sync($categoryIds);
                $lockedStory->tags()->sync(Tag::idsFromInput($tagInput));

                // sync() pivot không kích hoạt observer của Story nên
                // cache trang chủ không tự làm mới (xem store(), W1).
                app(HomeCacheService::class)->invalidate();
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

    public function destroy(Story $story, StoryDeletionService $deletion)
    {
        $this->ensureOwner($story);

        // Dùng chung StoryDeletionService với admin để thống nhất metadata
        // (deletion_reason, deleted_by). Đây chỉ là xóa mềm — ảnh bìa được
        // GIỮ LẠI để khôi phục truyện vẫn còn bìa; chỉ khi xóa vĩnh viễn
        // (forceDelete) mới xóa file ảnh, xem StoryDeletionService.
        $deleted = $deletion->delete($story, 'Tác giả tự xóa truyện.', auth()->id());
        abort_unless($deleted, 404);

        return redirect()->route('writer.stories.index')->with('success', 'Đã xóa truyện.');
    }

    private function ensureOwner(Story $story): void
    {
        abort_unless($story->user_id === auth()->id(), 403);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        // Cột slug là varchar(255): cắt base còn tối đa 250 ký tự rồi
        // mới thêm hậu tố trùng, để slug cuối cùng không bao giờ vượt
        // 255 ký tự rồi lỗi 22001 (V3).
        $base = mb_substr(Str::slug($title) ?: 'truyen', 0, 250);
        $slug = $base;
        $suffix = 1;

        while (Story::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $suffixText = '-'.$suffix++;
            $slug = mb_substr($base, 0, 255 - mb_strlen($suffixText)).$suffixText;
        }

        return $slug;
    }
}
