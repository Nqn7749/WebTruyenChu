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

class ChapterController extends Controller
{
    /**
     * Danh sách chương.
     */
    public function index(Story $story)
    {
        $chapters = $story->chapters()
            ->orderBy('chapter_number')
            ->paginate(20);

        return view(
            'admin.chapters.index',
            compact('story', 'chapters')
        );
    }

    /**
     * Form tạo chương.
     */
    public function create(Story $story)
    {
        $nextNumber = ($story->chapters()->max('chapter_number') ?? 0) + 1;

        return view(
            'admin.chapters.create',
            compact('story', 'nextNumber')
        );
    }

    /**
     * Tạo chương.
     */
    public function store(
        StoreChapterRequest $request,
        Story $story
    ) {
        $data = $request->validated();

        $data['story_id'] = $story->id;

        $data['slug'] = $this->generateUniqueSlug(
            $data['title'] ?? ('chuong-' . $data['chapter_number']),
            $story->id
        );

        $chapter = $story->chapters()->create($data);

        /*
        |--------------------------------------------------------------------------
        | Update story
        |--------------------------------------------------------------------------
        */

        $story->increment('chapter_count');

        $story->forceFill([
            'last_chapter_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Notification
        |--------------------------------------------------------------------------
        */

        if ((bool) $chapter->status === true) {
            $this->notifyFollowers(
                $story,
                $chapter
            );
        }

        return redirect()
            ->route(
                'admin.stories.chapters.index',
                $story
            )
            ->with(
                'success',
                'Tạo chương thành công.'
            );
    }

    /**
     * Form chỉnh sửa.
     */
    public function edit(Chapter $chapter)
    {
        $story = $chapter->story;

        return view(
            'admin.chapters.edit',
            compact('chapter', 'story')
        );
    }

    /**
     * Cập nhật chương.
     */
    public function update(
        UpdateChapterRequest $request,
        Chapter $chapter
    ) {
        $wasPublished = (bool) $chapter->status;

        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Update slug
        |--------------------------------------------------------------------------
        */

        $expectedTitle =
            $data['title']
            ?? ('chuong-' . $data['chapter_number']);

        if (
            Str::slug($expectedTitle)
            !== $chapter->slug
        ) {
            $data['slug'] = $this->generateUniqueSlug(
                $expectedTitle,
                $chapter->story_id,
                $chapter->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update chapter
        |--------------------------------------------------------------------------
        */

        $chapter->update($data);

        $story = $chapter->story;

        $story->forceFill([
            'last_chapter_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Nếu từ draft -> published
        |--------------------------------------------------------------------------
        */

        $isPublished = (bool) $chapter->status;

        if (!$wasPublished && $isPublished) {
            $this->notifyFollowers(
                $story,
                $chapter
            );
        }

        return redirect()
            ->route(
                'admin.stories.chapters.index',
                $story
            )
            ->with(
                'success',
                'Cập nhật chương thành công.'
            );
    }

    /**
     * Xóa chương.
     */
    public function destroy(Chapter $chapter)
    {
        $story = $chapter->story;

        $chapter->delete();

        $story->decrement('chapter_count');

        return redirect()
            ->route(
                'admin.stories.chapters.index',
                $story
            )
            ->with(
                'success',
                'Xóa chương thành công.'
            );
    }

    /**
     * Gửi notification tới người theo dõi truyện.
     */
    private function notifyFollowers(
        Story $story,
        Chapter $chapter
    ): void {
        User::query()
            ->whereHas('favorites', function ($query) use ($story) {
                $query
                    ->where('story_id', $story->id)
                    ->where('notify_new_chapter', true);
            })
            ->chunkById(200, function ($users) use ($chapter) {

                foreach ($users as $user) {

                    $user->notify(
                        new NewChapterPublished($chapter)
                    );
                }
            });
    }

    /**
     * Generate unique chapter slug.
     */
    private function generateUniqueSlug(
        string $title,
        int $storyId,
        ?int $ignoreId = null
    ): string {
        $slug = Str::slug($title);

        /*
        | Nếu title toàn ký tự đặc biệt
        */
        if ($slug === '') {
            $slug = 'chuong';
        }

        $original = $slug;
        $i = 1;

        while (
            Chapter::withTrashed()
                ->where('story_id', $storyId)
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        )
                )
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}