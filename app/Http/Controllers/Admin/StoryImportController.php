<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportStoryChapter;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryUrlImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StoryImportController extends Controller
{
    public function index()
    {
        return view('admin.story-import.index');
    }

    public function preview(Request $request, StoryUrlImportService $importer)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            $preview = $importer->preview($data['url']);
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['url' => Str::limit($exception->getMessage(), 300)]);
        }

        $token = Str::random(48);
        $preview['requested_by'] = $request->user()->id;
        Cache::put('story-url-import:'.$token, $preview, now()->addMinutes(30));

        return redirect()->route('admin.story-import.index', ['preview' => $token]);
    }

    public function showPreview(Request $request)
    {
        $token = $request->query('preview');
        $preview = is_string($token) ? Cache::get('story-url-import:'.$token) : null;

        return view('admin.story-import.index', [
            'preview' => $preview,
            'previewToken' => $preview ? $token : null,
        ]);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'chapters' => ['required', 'array', 'min:1', 'max:200'],
            'chapters.*' => ['required', 'integer', 'min:0', 'max:199'],
            'permission_confirmed' => ['accepted'],
        ]);

        $cacheKey = 'story-url-import:'.$data['token'];
        $preview = Cache::get($cacheKey);
        if (! is_array($preview)) {
            return redirect()->route('admin.story-import.index')
                ->withErrors(['url' => 'Phiên xem trước đã hết hạn. Hãy phân tích lại URL.']);
        }

        abort_unless((int) ($preview['requested_by'] ?? 0) === (int) $request->user()->id, 403);

        $selected = array_values(array_unique(array_map('intval', $data['chapters'])));
        foreach ($selected as $index) {
            if (! isset($preview['chapters'][$index])) {
                return redirect()->route('admin.story-import.index')
                    ->withErrors(['chapters' => 'Danh sách chương được chọn không hợp lệ.']);
            }
        }

        $sourceHash = hash('sha256', $preview['source_url']);
        if (Story::withTrashed()->where('source_hash', $sourceHash)->exists()) {
            return redirect()->route('admin.story-import.index')
                ->withErrors(['url' => 'Truyện từ URL này đã được nhập trước đó.']);
        }

        $chaptersToQueue = [];
        foreach ($selected as $offset => $index) {
            $item = $preview['chapters'][$index];
            $chapterHash = hash('sha256', $item['url']);

            if (Chapter::withTrashed()->where('source_hash', $chapterHash)->exists()) {
                continue;
            }

            $chaptersToQueue[] = new ImportStoryChapter(
                0,
                $item['title'],
                $item['url'],
                $chapterHash,
                $offset + 1,
            );
        }

        if ($chaptersToQueue === []) {
            return redirect()->route('admin.story-import.index')
                ->withErrors(['url' => 'Các chương được chọn đều đã tồn tại hoặc không có nội dung hợp lệ.']);
        }

        try {
            $story = DB::transaction(function () use ($request, $preview, $sourceHash): Story {
                $story = new Story;
                $story->forceFill([
                    'user_id' => $request->user()->id,
                    'title' => $preview['title'],
                    'slug' => (Str::slug($preview['title']) ?: 'truyen').'-'.Str::lower(Str::random(7)),
                    'author_name' => $preview['author'] ?: null,
                    'description' => $preview['description'] ?: null,
                    'status' => 'ongoing',
                    'status_publish' => false,
                    'source_url' => $preview['source_url'],
                    'source_hash' => $sourceHash,
                ]);
                $story->save();

                return $story;
            });

            $jobs = array_map(function (ImportStoryChapter $job) use ($story): ImportStoryChapter {
                $job->storyId = $story->id;

                return $job;
            }, $chaptersToQueue);

            Bus::batch($jobs)
                ->name('Nhập truyện: '.Str::limit($story->title, 100, ''))
                ->allowFailures()
                ->dispatch();
        } catch (Throwable $exception) {
            if (isset($story) && $story->exists) {
                $story->forceDelete();
            }

            return redirect()->route('admin.story-import.index')
                ->withErrors(['url' => Str::limit($exception->getMessage(), 300)]);
        }

        return redirect()->route('admin.stories.index')
            ->with('success', 'Đã đưa '.$story->title.' cùng '.count($jobs).' chương vào hàng đợi. Nội dung sẽ được nhập nền ở trạng thái bản nháp.');
    }
}
