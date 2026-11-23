<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\Tag;
use App\Services\HomeCacheService;
use App\Services\StoryFileImportService;
use App\Services\StoryStatsService;
use App\Support\TagInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lets an author publish a story they wrote from a .txt or .zip file: upload, review the
 * detected chapters and story details, then create the story and its chapters in one step.
 */
class StoryFileImportController extends Controller
{
    private const MAX_CHAPTERS = 200;

    private const CACHE_PREFIX = 'writer-file-import:';

    public function create(Request $request)
    {
        $token = $request->query('preview');
        $preview = is_string($token) && strlen($token) === 48 ? Cache::get(self::CACHE_PREFIX.$token) : null;

        if (is_array($preview) && (int) ($preview['requested_by'] ?? 0) !== (int) $request->user()->id) {
            $preview = null;
        }

        return view('writer.stories.import', [
            'preview' => $preview,
            'previewToken' => $preview ? $token : null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'popularTags' => Tag::query()->withCount('stories')->orderByDesc('stories_count')->orderBy('name')->limit(12)->get(['id', 'name']),
            'maxChapters' => self::MAX_CHAPTERS,
        ]);
    }

    public function upload(Request $request, StoryFileImportService $files)
    {
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

        $title = trim(preg_replace('/[_\s]+/u', ' ', pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)) ?? '');

        Cache::put(self::CACHE_PREFIX.$token, [
            'file' => $stored,
            'extension' => $extension,
            'title' => Str::limit(str_replace(['<', '>'], '', $title), 255, ''),
            'chapters' => array_map(fn (array $chapter): array => [
                'title' => Str::limit($chapter['title'], 255, ''),
                'number' => $chapter['number'],
            ], $chapters),
            'requested_by' => $request->user()->id,
        ], now()->addMinutes(30));

        return redirect()->route('writer.stories.import', ['preview' => $token]);
    }

    public function store(Request $request, StoryFileImportService $files, StoryStatsService $stats)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'chapters' => ['required', 'array', 'min:1', 'max:'.self::MAX_CHAPTERS],
            'chapters.*' => ['required', 'integer', 'min:0', 'max:'.(self::MAX_CHAPTERS - 1)],
            'authorship_confirmed' => ['accepted'],
            'publish_chapters' => ['nullable', 'boolean'],
            'title' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'author_name' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'description' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', 'in:ongoing,completed,paused'],
            'categories' => ['required', 'array', 'min:1', 'max:10'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:600', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($message = TagInput::validate((string) $value)) {
                    $fail($message);
                }
            }],
            // Raster images only: SVG can carry script and the file is served from the public disk.
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [
            'authorship_confirmed.accepted' => 'Bạn cần xác nhận mình là tác giả hoặc có quyền đăng tải nội dung này.',
            'title.required' => 'Vui lòng nhập tên truyện.',
            'categories.required' => 'Hãy chọn ít nhất một thể loại.',
            'categories.min' => 'Hãy chọn ít nhất một thể loại.',
            'chapters.required' => 'Hãy chọn ít nhất một chương để đăng.',
            'chapters.min' => 'Hãy chọn ít nhất một chương để đăng.',
            'status.in' => 'Tình trạng truyện không hợp lệ.',
            'cover_image.mimes' => 'Ảnh bìa phải là JPEG, PNG hoặc WebP.',
            'cover_image.max' => 'Ảnh bìa tối đa 3 MB.',
        ]);

        $cacheKey = self::CACHE_PREFIX.$data['token'];
        $preview = Cache::get($cacheKey);
        if (! is_array($preview) || (int) ($preview['requested_by'] ?? 0) !== (int) $request->user()->id) {
            return redirect()->route('writer.stories.import')
                ->withErrors(['file' => 'Phiên xem trước đã hết hạn. Hãy tải tệp lên lại.']);
        }

        $path = Storage::disk('local')->path((string) $preview['file']);
        if (! is_file($path)) {
            return redirect()->route('writer.stories.import')
                ->withErrors(['file' => 'Tệp tải lên không còn tồn tại. Hãy tải lại.']);
        }

        try {
            $all = $files->chapters($path, (string) $preview['extension'], self::MAX_CHAPTERS);
        } catch (Throwable $exception) {
            return redirect()->route('writer.stories.import')->withErrors(['file' => Str::limit($exception->getMessage(), 300)]);
        }

        $selected = array_values(array_unique(array_map('intval', $data['chapters'])));
        sort($selected);
        foreach ($selected as $index) {
            if (! isset($all[$index])) {
                return redirect()->route('writer.stories.import', ['preview' => $data['token']])
                    ->withErrors(['chapters' => 'Danh sách chương được chọn không hợp lệ.']);
            }
        }

        $publish = $request->boolean('publish_chapters');
        $cover = $request->file('cover_image')?->store('covers', 'public');
        if ($request->hasFile('cover_image') && ! $cover) {
            return back()->withInput()->withErrors(['cover_image' => 'Không thể lưu ảnh bìa.']);
        }

        try {
            $story = DB::transaction(function () use ($request, $data, $cover, $selected, $all, $files, $publish, $stats): Story {
                // Same as a story posted from the form: public immediately, flags set by the system only.
                $story = $request->user()->stories()->create([
                    'title' => $data['title'],
                    'slug' => (Str::slug($data['title']) ?: 'truyen').'-'.Str::lower(Str::random(7)),
                    'author_name' => ($data['author_name'] ?? '') ?: $request->user()->name,
                    'description' => ($data['description'] ?? '') ?: null,
                    'cover_image' => $cover,
                    'status' => $data['status'],
                ]);
                $story->forceFill([
                    'status_publish' => true,
                    'is_hot' => false,
                    'is_featured' => false,
                    'is_recommended' => false,
                ])->save();

                $story->categories()->sync($data['categories']);
                $story->tags()->sync(Tag::idsFromInput($data['tags'] ?? ''));

                foreach ($selected as $index) {
                    $item = $all[$index];

                    $chapter = new Chapter;
                    $chapter->forceFill([
                        'story_id' => $story->id,
                        'chapter_number' => $item['number'],
                        'title' => Str::limit($item['title'], 255, ''),
                        'slug' => 'chuong-'.$item['number'].'-'.Str::lower(Str::random(6)),
                        'content' => $files->toHtml($item['content']),
                        'status' => $publish,
                    ]);
                    $chapter->save();
                }

                $stats->refreshChapterStats($story);
                // sync() on pivots does not trigger the Story observer, so refresh the home cache by hand.
                app(HomeCacheService::class)->invalidate();

                return $story;
            });
        } catch (Throwable $exception) {
            if ($cover) {
                Storage::disk('public')->delete($cover);
            }

            // Log the real cause (it may contain SQL) and show the author a plain message.
            report($exception);

            return redirect()->route('writer.stories.import', ['preview' => $data['token']])
                ->withInput()
                ->withErrors(['file' => 'Không thể lưu truyện. Hãy kiểm tra lại thông tin (ví dụ ký tự lạ trong tên hoặc giới thiệu) rồi thử lại.']);
        }

        Storage::disk('local')->delete((string) $preview['file']);
        Cache::forget($cacheKey);

        return redirect()->route('writer.stories.index')
            ->with('success', 'Đã đăng truyện '.$story->title.' với '.count($selected).' chương'
                .($publish ? ' công khai.' : ' ở trạng thái nháp. Hãy mở từng chương để đăng khi sẵn sàng.'));
    }
}
