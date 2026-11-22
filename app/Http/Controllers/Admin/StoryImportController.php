<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryUrlImportService;
use Illuminate\Http\Request;
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

    public function import(Request $request, StoryUrlImportService $importer)
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

        try {
            $story = DB::transaction(function () use ($request, $preview, $selected, $sourceHash, $importer) {
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

                $chapterNumber = 1;
                foreach ($selected as $index) {
                    $item = $preview['chapters'][$index];
                    $chapterHash = hash('sha256', $item['url']);
                    if (Chapter::withTrashed()->where('source_hash', $chapterHash)->exists()) {
                        continue;
                    }

                    $content = $importer->fetchChapter($item['url']);
                    $chapter = new Chapter;
                    $chapter->forceFill([
                        'story_id' => $story->id,
                        'chapter_number' => $chapterNumber,
                        'title' => $item['title'],
                        'slug' => 'chuong-'.$chapterNumber.'-'.Str::lower(Str::random(6)),
                        'content' => $content,
                        'status' => false,
                        'source_url' => $item['url'],
                        'source_hash' => $chapterHash,
                    ]);
                    $chapter->save();
                    $chapterNumber++;
                }

                if ($chapterNumber === 1) {
                    throw new RuntimeException('Các chương được chọn đều đã tồn tại hoặc không có nội dung hợp lệ.');
                }

                return $story;
            });
        } catch (Throwable $exception) {
            return redirect()->route('admin.story-import.index')
                ->withErrors(['url' => Str::limit($exception->getMessage(), 300)]);
        }

        return redirect()->route('admin.stories.index')
            ->with('success', 'Đã nhập truyện "'.$story->title.'" cùng '.$story->chapters()->count().' chương ở trạng thái bản nháp.');
    }
}
