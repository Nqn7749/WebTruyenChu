<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChapterRequest;
use App\Http\Requests\Admin\UpdateChapterRequest;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;

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

        $chapter = Chapter::create($data);

        $story->increment('chapter_count');
        $story->forceFill(['last_chapter_at' => now()])->save();

        // Chỉ thông báo khi chương được đăng công khai ngay (status = true)
        if ($chapter->status) {
            $this->notifyFollowers($story, $chapter);
        }

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
        $wasPublished = $chapter->status;

        $data = $request->validated();
        $expectedTitle = $data['title'] ?? ('chuong-' . $data['chapter_number']);
        if (Str::slug($expectedTitle) !== $chapter->slug) {
            $data['slug'] = $this->generateUniqueSlug($expectedTitle, $chapter->story_id, $chapter->id);
        }

        $chapter->update($data);

        $chapter->story->forceFill(['last_chapter_at' => now()])->save();

        // Trường hợp chương trước đó là nháp (status=false), giờ mới publish → cũng cần báo
        if (! $wasPublished && $chapter->status) {
            $this->notifyFollowers($chapter->story, $chapter);
        }

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

    /**
     * Gửi thông báo tới các user đã yêu thích truyện và bật notify_new_chapter.
     * Dùng chunk để tránh load hết user vào memory nếu truyện có rất nhiều follower.
     */
    private function notifyFollowers(Story $story, Chapter $chapter): void
    {
        User::whereHas('favorites', function ($q) use ($story) {
                $q->where('story_id', $story->id)
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