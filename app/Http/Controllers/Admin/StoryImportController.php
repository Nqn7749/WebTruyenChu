<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportStoryChapter;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryUrlImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    public function batch(string $batchId)
    {
        $batch = Bus::findBatch($batchId);
        abort_if($batch === null, 404);

        // Laravel keeps failed jobs inside pending_jobs when failures are allowed,
        // so a batch with failures never gets finished_at. Derive the numbers here.
        $failed = $batch->failedJobs;
        $succeeded = $batch->processedJobs();
        $waiting = max(0, $batch->pendingJobs - $failed);
        $done = $batch->cancelled() || $waiting === 0;
        $progress = $batch->totalJobs > 0
            ? (int) round(($succeeded + $failed) / $batch->totalJobs * 100)
            : 0;

        $failedJobIds = $batch->failedJobIds;
        $failures = $failedJobIds === [] ? collect() : DB::table('failed_jobs')
            ->whereIn('uuid', $failedJobIds)
            ->get(['uuid', 'exception', 'failed_at'])
            ->map(fn ($job) => [
                'uuid' => $job->uuid,
                'message' => $this->failureSummary((string) $job->exception),
                'failed_at' => $job->failed_at,
            ]);

        $stats = compact('failed', 'succeeded', 'waiting', 'done', 'progress');

        return view('admin.story-import.batch', compact('batch', 'failures', 'stats'));
    }

    public function retry(string $batchId)
    {
        $batch = Bus::findBatch($batchId);
        abort_if($batch === null, 404);

        if ($batch->failedJobs === 0 || $batch->cancelled() || $batch->pendingJobs - $batch->failedJobs > 0) {
            return redirect()->route('admin.story-import.batch', $batchId)
                ->withErrors(['retry' => 'Chỉ có thể thử lại khi batch đã kết thúc và còn chương lỗi.']);
        }

        Artisan::call('queue:retry-batch', ['id' => [$batchId]]);

        return redirect()->route('admin.story-import.batch', $batchId)
            ->with('success', 'Đã đưa '.$batch->failedJobs.' chương lỗi vào hàng đợi để thử lại.');
    }

    private function failureSummary(string $exception): string
    {
        $firstLine = trim(Str::before($exception, "\n"));
        // Drop the "ClassName: " prefix and the trailing " in /path/file.php:123".
        $firstLine = preg_replace('/^[\\\w]+: /', '', $firstLine) ?? $firstLine;
        $firstLine = preg_replace('/ in \S+:\d+$/', '', $firstLine) ?? $firstLine;

        return Str::limit($firstLine, 300);
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

            $chaptersToQueue[] = [
                'title' => $item['title'],
                'url' => $item['url'],
                'source_hash' => $chapterHash,
                // Keep the number parsed from the source title (e.g. "Chương 5") so
                // importing a subset does not renumber chapters from 1.
                'chapter_number' => max(1, (int) ($item['number'] ?? $offset + 1)),
            ];
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

            $jobs = array_map(
                fn (array $chapter): ImportStoryChapter => new ImportStoryChapter(
                    $story->id,
                    $chapter['title'],
                    $chapter['url'],
                    $chapter['source_hash'],
                    $chapter['chapter_number'],
                ),
                $chaptersToQueue,
            );

            $batch = Bus::batch($jobs)
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

        return redirect()->route('admin.story-import.batch', $batch->id)
            ->with('success', 'Đã đưa '.$story->title.' cùng '.count($jobs).' chương vào hàng đợi. Nội dung sẽ được nhập nền ở trạng thái bản nháp.');
    }
}
