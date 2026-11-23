<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkChapterNotifications;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryFileImportService;
use App\Services\StoryStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adds many chapters to a story the author already created, from a .txt or .zip file
 * (the same file formats as "Đăng truyện từ tệp").
 */
class ChapterFileImportController extends Controller
{
    private const MAX_CHAPTERS = 200;

    private const CACHE_PREFIX = 'writer-chapter-import:';

    public function create(Request $request, Story $story)
    {
        $this->ensureOwner($story);

        $token = $request->query('preview');
        $preview = is_string($token) && strlen($token) === 48 ? Cache::get(self::CACHE_PREFIX.$token) : null;

        if (is_array($preview) && ((int) ($preview['requested_by'] ?? 0) !== (int) $request->user()->id
            || (int) ($preview['story_id'] ?? 0) !== $story->id)) {
            $preview = null;
        }

        $existing = $story->chapters()->pluck('chapter_number')->map(fn ($n) => (int) $n)->all();
        $conflicts = $preview
            ? array_values(array_filter(
                array_keys($preview['chapters']),
                fn (int $index): bool => in_array((int) $preview['chapters'][$index]['number'], $existing, true),
            ))
            : [];

        return view('writer.chapters.import', [
            'story' => $story,
            'preview' => $preview,
            'previewToken' => $preview ? $token : null,
            'conflicts' => $conflicts,
            'lastNumber' => $existing === [] ? 0 : max($existing),
            'chapterCount' => count($existing),
            'maxChapters' => self::MAX_CHAPTERS,
        ]);
    }

    public function upload(Request $request, Story $story, StoryFileImportService $files)
    {
        $this->ensureOwner($story);

        $request->validate([
            'file' => ['required', 'file', 'mimes:txt,zip', 'max:10240'],
        ], [
            'file.mimes' => 'Chỉ hỗ trợ tệp .txt hoặc .zip.',
            'file.max' => 'Tệp tối đa 10 MB.',
        ]);

        $upload = $request->file('file');
        $extension = strtolower($upload->getClientOriginalExtension());

        try {
            $chapters = $files->chapters($upload->getRealPath(), $extension, self::MAX_CHAPTERS);
        } catch (Throwable $exception) {
            return back()->withErrors(['file' => Str::limit($exception->getMessage(), 300)]);
        }

        $files->pruneUploads();

        $token = Str::random(48);
        $stored = $upload->storeAs('story-imports', $token.'.'.$extension, 'local');
        if (! $stored) {
            return back()->withErrors(['file' => 'Không thể lưu tệp tải lên.']);
        }

        Cache::put(self::CACHE_PREFIX.$token, [
            'file' => $stored,
            'extension' => $extension,
            'story_id' => $story->id,
            'chapters' => array_map(fn (array $chapter): array => [
                'title' => Str::limit($chapter['title'], 255, ''),
                'number' => $chapter['number'],
            ], $chapters),
            'requested_by' => $request->user()->id,
        ], now()->addMinutes(30));

        return redirect()->route('writer.stories.chapters.import', [$story, 'preview' => $token]);
    }

    public function store(Request $request, Story $story, StoryFileImportService $files, StoryStatsService $stats)
    {
        $this->ensureOwner($story);

        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'chapters' => ['required', 'array', 'min:1', 'max:'.self::MAX_CHAPTERS],
            'chapters.*' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_CHAPTERS - 1)],
            'numbering' => ['required', 'in:file,append'],
            'publish_chapters' => ['nullable', 'boolean'],
        ], [
            'chapters.required' => 'Hãy chọn ít nhất một chương để thêm.',
            'chapters.min' => 'Hãy chọn ít nhất một chương để thêm.',
            'numbering.required' => 'Hãy chọn cách đánh số chương.',
        ]);

        $cacheKey = self::CACHE_PREFIX.$data['token'];
        $preview = Cache::get($cacheKey);
        if (! is_array($preview)
            || (int) ($preview['requested_by'] ?? 0) !== (int) $request->user()->id
            || (int) ($preview['story_id'] ?? 0) !== $story->id) {
            return redirect()->route('writer.stories.chapters.import', $story)
                ->withErrors(['file' => 'Phiên xem trước đã hết hạn. Hãy tải tệp lên lại.']);
        }

        $path = Storage::disk('local')->path((string) $preview['file']);
        if (! is_file($path)) {
            return redirect()->route('writer.stories.chapters.import', $story)
                ->withErrors(['file' => 'Tệp tải lên không còn tồn tại. Hãy tải lại.']);
        }

        try {
            $all = $files->chapters($path, (string) $preview['extension'], self::MAX_CHAPTERS);
        } catch (Throwable $exception) {
            return redirect()->route('writer.stories.chapters.import', $story)
                ->withErrors(['file' => Str::limit($exception->getMessage(), 300)]);
        }

        $selected = array_values(array_unique(array_map('intval', $data['chapters'])));
        sort($selected);
        foreach ($selected as $index) {
            if (! isset($all[$index])) {
                return redirect()->route('writer.stories.chapters.import', [$story, 'preview' => $data['token']])
                    ->withErrors(['chapters' => 'Danh sách chương được chọn không hợp lệ.']);
            }
        }

        $publish = $request->boolean('publish_chapters');
        $append = $data['numbering'] === 'append';

        try {
            $createdIds = DB::transaction(function () use ($story, $selected, $all, $files, $publish, $append, $stats): array {
                // Lock the story so two imports (or an import and a manual add) cannot take the same numbers.
                $lockedStory = Story::query()->lockForUpdate()->findOrFail($story->id);
                $existing = $lockedStory->chapters()->pluck('chapter_number')->map(fn ($n) => (int) $n)->all();

                $numbers = [];
                if ($append) {
                    $next = ($existing === [] ? 0 : max($existing)) + 1;
                    foreach ($selected as $index) {
                        $numbers[$index] = $next++;
                    }
                } else {
                    $taken = [];
                    foreach ($selected as $index) {
                        $number = (int) $all[$index]['number'];
                        if (in_array($number, $existing, true)) {
                            $taken[] = $number;
                        }
                        $numbers[$index] = $number;
                    }
                    if ($taken !== []) {
                        throw ValidationException::withMessages([
                            'chapters' => 'Các số chương đã có trong truyện: '.implode(', ', array_slice($taken, 0, 15))
                                .(count($taken) > 15 ? '...' : '').'. Hãy bỏ chọn các chương đó hoặc chọn "Nối tiếp sau chương cuối".',
                        ]);
                    }
                    if (count(array_unique($numbers)) !== count($numbers)) {
                        throw ValidationException::withMessages([
                            'chapters' => 'Tệp có hai chương trùng số. Hãy chọn "Nối tiếp sau chương cuối".',
                        ]);
                    }
                }

                $ids = [];
                foreach ($selected as $index) {
                    $item = $all[$index];

                    $chapter = new Chapter;
                    $chapter->forceFill([
                        'story_id' => $lockedStory->id,
                        'chapter_number' => $numbers[$index],
                        'title' => Str::limit($item['title'], 255, ''),
                        'slug' => 'chuong-'.$numbers[$index].'-'.Str::lower(Str::random(6)),
                        'content' => $files->toHtml($item['content']),
                        'status' => $publish,
                    ]);
                    $chapter->save();
                    $ids[] = $chapter->id;
                }

                $stats->refreshChapterStats($lockedStory);

                return $ids;
            });
        } catch (ValidationException $exception) {
            return redirect()->route('writer.stories.chapters.import', [$story, 'preview' => $data['token']])
                ->withInput()
                ->withErrors($exception->errors());
        } catch (Throwable $exception) {
            // Log the real cause (it may contain SQL) and show the author a plain message.
            report($exception);

            return redirect()->route('writer.stories.chapters.import', [$story, 'preview' => $data['token']])
                ->withInput()
                ->withErrors(['file' => 'Không thể lưu các chương. Hãy kiểm tra lại nội dung tệp rồi thử lại.']);
        }

        Storage::disk('local')->delete((string) $preview['file']);
        Cache::forget($cacheKey);

        // Followers hear about newly published chapters, in batches so one job stays short.
        if ($publish && $story->status_publish) {
            foreach (array_chunk($createdIds, 100) as $chunk) {
                SendBulkChapterNotifications::dispatch($chunk);
            }
        }

        return redirect()->route('writer.stories.chapters.index', $story)
            ->with('success', 'Đã thêm '.count($createdIds).' chương'
                .($publish ? ' và đăng công khai.' : ' ở trạng thái nháp. Mở từng chương để đăng khi sẵn sàng.'));
    }

    private function ensureOwner(Story $story): void
    {
        abort_unless($story->user_id === auth()->id(), 403);
    }
}
