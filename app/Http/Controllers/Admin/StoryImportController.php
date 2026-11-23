<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportStoryChapter;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\Tag;
use App\Services\StoryFileImportService;
use App\Services\StoryUrlImportService;
use App\Support\TagInput;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'popularTags' => Tag::query()->withCount('stories')->orderByDesc('stories_count')->orderBy('name')->limit(12)->get(['id', 'name']),
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

    /**
     * Upload a .txt or .zip of chapters and show the same edit-and-select preview as a URL import.
     */
    public function uploadFile(Request $request, StoryFileImportService $files)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:txt,zip', 'max:20480'],
        ], [
            'file.mimes' => 'Chỉ hỗ trợ tệp .txt hoặc .zip.',
            'file.max' => 'Tệp tối đa 20 MB.',
        ]);

        $upload = $request->file('file');
        $extension = strtolower($upload->getClientOriginalExtension());

        try {
            $chapters = $files->chapters($upload->getRealPath(), $extension);
        } catch (Throwable $exception) {
            return back()->withErrors(['file' => Str::limit($exception->getMessage(), 300)]);
        }

        $files->pruneUploads();

        $token = Str::random(48);
        $stored = $upload->storeAs('story-imports', $token.'.'.$extension, 'local');
        if (! $stored) {
            return back()->withErrors(['file' => 'Không thể lưu tệp tải lên.']);
        }

        $title = trim(preg_replace('/[_\s]+/u', ' ', pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)) ?? '');

        Cache::put('story-url-import:'.$token, [
            'source_type' => 'file',
            'file' => $stored,
            'extension' => $extension,
            'source_url' => '',
            'host' => 'Tệp tải lên',
            'title' => Str::limit(str_replace(['<', '>'], '', $title), 255, ''),
            'author' => '',
            'description' => '',
            'cover_url' => '',
            'chapters' => array_map(fn (array $chapter): array => ['title' => Str::limit($chapter['title'], 255, ''), 'number' => $chapter['number']], $chapters),
            'requested_by' => $request->user()->id,
        ], now()->addMinutes(30));

        return redirect()->route('admin.story-import.index', ['preview' => $token]);
    }

    /**
     * Create the story and the selected chapters from the uploaded file. The content is local,
     * so no queue is needed: everything is saved in one transaction as unpublished drafts.
     */
    public function importFile(Request $request, StoryFileImportService $files, StoryUrlImportService $importer)
    {
        $this->fillDetailsFromPreview($request);

        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'chapters' => ['required', 'array', 'min:1', 'max:'.StoryFileImportService::MAX_CHAPTERS],
            'chapters.*' => ['required', 'integer', 'min:0', 'max:'.(StoryFileImportService::MAX_CHAPTERS - 1)],
            'permission_confirmed' => ['accepted'],
        ] + $this->detailRules(), [
            'permission_confirmed.accepted' => 'Bạn cần xác nhận có quyền nhập và lưu trữ nội dung này.',
            'chapters.required' => 'Hãy chọn ít nhất một chương để nhập.',
        ]);

        $cacheKey = 'story-url-import:'.$data['token'];
        $preview = Cache::get($cacheKey);
        if (! is_array($preview) || ($preview['source_type'] ?? '') !== 'file') {
            return redirect()->route('admin.story-import.index')
                ->withErrors(['file' => 'Phiên xem trước đã hết hạn. Hãy tải tệp lên lại.']);
        }

        abort_unless((int) ($preview['requested_by'] ?? 0) === (int) $request->user()->id, 403);

        $path = Storage::disk('local')->path((string) $preview['file']);
        if (! is_file($path)) {
            return redirect()->route('admin.story-import.index')
                ->withErrors(['file' => 'Tệp tải lên không còn tồn tại. Hãy tải lại.']);
        }

        try {
            $all = $files->chapters($path, (string) $preview['extension']);
        } catch (Throwable $exception) {
            return redirect()->route('admin.story-import.index')->withErrors(['file' => Str::limit($exception->getMessage(), 300)]);
        }

        $selected = array_values(array_unique(array_map('intval', $data['chapters'])));
        sort($selected);
        foreach ($selected as $index) {
            if (! isset($all[$index])) {
                return redirect()->route('admin.story-import.index')
                    ->withErrors(['chapters' => 'Danh sách chương được chọn không hợp lệ.']);
            }
        }

        $cover = $this->storeCover($request, $importer);

        try {
            $story = DB::transaction(function () use ($request, $data, $cover, $selected, $all, $files): Story {
                $story = $this->createStory($request, $data, $cover);

                foreach ($selected as $index) {
                    $item = $all[$index];

                    $chapter = new Chapter;
                    $chapter->forceFill([
                        'story_id' => $story->id,
                        'chapter_number' => $item['number'],
                        'title' => Str::limit($item['title'], 255, ''),
                        'slug' => 'chuong-'.$item['number'].'-'.Str::lower(Str::random(6)),
                        'content' => $files->toHtml($item['content']),
                        'status' => false,
                    ]);
                    $chapter->save();
                }

                return $story;
            });
        } catch (Throwable $exception) {
            if ($cover) {
                Storage::disk('public')->delete($cover);
            }

            report($exception);

            return redirect()->route('admin.story-import.index')
                ->withErrors(['file' => $exception instanceof QueryException
                    ? 'Không thể lưu truyện. Hãy kiểm tra lại thông tin (ví dụ ký tự lạ trong tên hoặc giới thiệu) rồi thử lại.'
                    : Str::limit($exception->getMessage(), 300)]);
        }

        Storage::disk('local')->delete((string) $preview['file']);
        Cache::forget($cacheKey);

        return redirect()->route('admin.story-import.index')
            ->with('success', 'Đã nhập '.$story->title.' với '.count($selected).' chương ở trạng thái bản nháp.');
    }

    private function failureSummary(string $exception): string
    {
        $firstLine = trim(Str::before($exception, "\n"));
        // Drop the "ClassName: " prefix and the trailing " in /path/file.php:123".
        $firstLine = preg_replace('/^[\\\w]+: /', '', $firstLine) ?? $firstLine;
        $firstLine = preg_replace('/ in \S+:\d+$/', '', $firstLine) ?? $firstLine;

        return Str::limit($firstLine, 300);
    }

    /**
     * Story details the admin may edit before importing (shared by URL and file imports).
     *
     * @return array<string, mixed>
     */
    private function detailRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'author_name' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'description' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', 'in:ongoing,completed,paused'],
            'categories' => ['nullable', 'array', 'max:10'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:600', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($message = TagInput::validate((string) $value)) {
                    $fail($message);
                }
            }],
            // Raster images only (no SVG: it can carry script and the file is served from the public disk).
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'cover_url' => ['nullable', 'url', 'max:2048', 'starts_with:https://'],
        ];
    }

    /**
     * Save the chosen cover: an uploaded file wins over a cover URL, which is fetched
     * through the importer's SSRF-safe client. Returns the public-disk path or null.
     */
    private function storeCover(Request $request, StoryUrlImportService $importer): ?string
    {
        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('covers', 'public');
            if (! $path) {
                throw ValidationException::withMessages(['cover_image' => 'Không thể lưu ảnh bìa.']);
            }

            return $path;
        }

        $url = trim((string) $request->input('cover_url'));
        if ($url === '') {
            return null;
        }

        try {
            $cover = $importer->fetchCover($url);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['cover_url' => 'Không tải được ảnh bìa: '.Str::limit($exception->getMessage(), 200)]);
        }

        $path = 'covers/'.Str::random(40).'.'.$cover['extension'];
        Storage::disk('public')->put($path, $cover['content']);

        return $path;
    }

    /**
     * Create the (unpublished) story from the edited details and attach categories and tags.
     * Must run inside the caller's transaction.
     *
     * @param  array<string, mixed>  $data  validated detail fields
     * @param  array<string, mixed>  $extra  forced columns such as source_url and source_hash
     */
    private function createStory(Request $request, array $data, ?string $cover, array $extra = []): Story
    {
        $story = new Story;
        $story->forceFill([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => (Str::slug($data['title']) ?: 'truyen').'-'.Str::lower(Str::random(7)),
            'author_name' => ($data['author_name'] ?? '') ?: null,
            'description' => ($data['description'] ?? '') ?: null,
            'cover_image' => $cover,
            'status' => $data['status'],
            'status_publish' => false,
        ] + $extra);
        $story->save();

        $story->categories()->sync($data['categories'] ?? []);
        $story->tags()->sync(Tag::idsFromInput($data['tags'] ?? ''));

        return $story;
    }

    /**
     * Detail fields that are missing from the request fall back to what the preview found,
     * so a client that only sends token + chapters still imports with the detected details.
     */
    private function fillDetailsFromPreview(Request $request): void
    {
        $token = $request->input('token');
        $preview = is_string($token) && strlen($token) === 48 ? Cache::get('story-url-import:'.$token) : null;

        if (is_array($preview)) {
            $request->mergeIfMissing([
                'title' => $preview['title'] ?? '',
                'author_name' => $preview['author'] ?? '',
                'description' => $preview['description'] ?? '',
                'status' => 'ongoing',
            ]);
        }
    }

    public function import(Request $request, StoryUrlImportService $importer)
    {
        $this->fillDetailsFromPreview($request);

        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'chapters' => ['required', 'array', 'min:1', 'max:200'],
            'chapters.*' => ['required', 'integer', 'min:0', 'max:199'],
            'permission_confirmed' => ['accepted'],
        ] + $this->detailRules());

        $cacheKey = 'story-url-import:'.$data['token'];
        $preview = Cache::get($cacheKey);
        if (! is_array($preview)) {
            return redirect()->route('admin.story-import.index')
                ->withErrors(['url' => 'Phiên xem trước đã hết hạn. Hãy phân tích lại URL.']);
        }

        abort_unless((int) ($preview['requested_by'] ?? 0) === (int) $request->user()->id, 403);
        abort_if(($preview['source_type'] ?? 'url') !== 'url', 422, 'Phiên xem trước này dành cho nhập từ tệp.');

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

        // Download the cover before the transaction so network I/O never holds DB locks.
        $cover = $this->storeCover($request, $importer);

        try {
            $story = DB::transaction(fn (): Story => $this->createStory($request, $data, $cover, [
                'source_url' => $preview['source_url'],
                'source_hash' => $sourceHash,
            ]));

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
            if ($cover) {
                Storage::disk('public')->delete($cover);
            }

            report($exception);

            return redirect()->route('admin.story-import.index')
                ->withErrors(['url' => $exception instanceof QueryException
                    ? 'Không thể lưu truyện. Hãy kiểm tra lại thông tin (ví dụ ký tự lạ trong tên hoặc giới thiệu) rồi thử lại.'
                    : Str::limit($exception->getMessage(), 300)]);
        }

        return redirect()->route('admin.story-import.batch', $batch->id)
            ->with('success', 'Đã đưa '.$story->title.' cùng '.count($jobs).' chương vào hàng đợi. Nội dung sẽ được nhập nền ở trạng thái bản nháp.');
    }
}
