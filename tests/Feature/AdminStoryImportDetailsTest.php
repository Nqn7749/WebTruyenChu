<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Role;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Services\StoryFileImportService;
use App\Services\StoryUrlImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function importDetailsAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create(['role_id' => $role->id, 'status' => true, 'email_verified_at' => now()]);
}

function pngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

function urlPreviewToken(User $admin): string
{
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Truyện gốc"><meta property="og:image" content="https://8.8.4.4/cover.png"><meta name="author" content="Tác giả gốc"></head><body><a href="/chapter/1">Chương 1</a></body></html>'),
    ]);

    $preview = app(StoryUrlImportService::class)->preview('https://8.8.8.8/story');
    $preview['requested_by'] = $admin->id;
    $token = str_repeat('b', 48);
    Cache::put('story-url-import:'.$token, $preview, now()->addMinutes(30));

    return $token;
}

function makeZip(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return $path;
}

test('the preview detects the cover image url from og:image', function () {
    $preview = app(StoryUrlImportService::class);
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="T"><meta property="og:image" content="/img/c.jpg"></head><body><a href="/chapter/1">Chương 1</a></body></html>'),
    ]);

    expect($preview->preview('https://8.8.8.8/story')['cover_url'])->toBe('https://8.8.8.8/img/c.jpg');
});

test('admin edits details, picks categories and tags before a URL import', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Storage::fake('public');
    Cache::flush();

    $admin = importDetailsAdmin();
    $token = urlPreviewToken($admin);
    $category = Category::factory()->create();

    Http::fake(['https://8.8.4.4/cover.png' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $this->actingAs($admin)
        ->post(route('admin.story-import.store'), [
            'token' => $token,
            'chapters' => [0],
            'permission_confirmed' => '1',
            'title' => 'Tên đã sửa',
            'author_name' => 'Tác giả đã sửa',
            'description' => 'Giới thiệu đã sửa',
            'status' => 'completed',
            'categories' => [$category->id],
            'tags' => 'Hệ Thống, Báo Thù',
            'cover_url' => 'https://8.8.4.4/cover.png',
        ])->assertSessionHasNoErrors()->assertRedirect();

    $story = Story::query()->where('title', 'Tên đã sửa')->firstOrFail();

    expect($story->author_name)->toBe('Tác giả đã sửa')
        ->and($story->description)->toBe('Giới thiệu đã sửa')
        ->and($story->status)->toBe('completed')
        ->and($story->status_publish)->toBeFalse()
        ->and($story->categories()->pluck('categories.id')->all())->toBe([$category->id])
        ->and($story->tags()->pluck('name')->sort()->values()->all())->toBe(['Báo Thù', 'Hệ Thống'])
        ->and($story->source_hash)->toBe(hash('sha256', 'https://8.8.8.8/story'));

    expect($story->cover_image)->toStartWith('covers/')->toEndWith('.png');
    Storage::disk('public')->assertExists($story->cover_image);
});

test('an uploaded cover wins over the cover url and svg uploads are rejected', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Storage::fake('public');
    Cache::flush();

    $admin = importDetailsAdmin();
    $token = urlPreviewToken($admin);

    $this->actingAs($admin)
        ->post(route('admin.story-import.store'), [
            'token' => $token, 'chapters' => [0], 'permission_confirmed' => '1',
            'cover_image' => UploadedFile::fake()->createWithContent('cover.svg', '<svg onload="alert(1)"></svg>'),
        ])->assertSessionHasErrors('cover_image');

    expect(Story::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->post(route('admin.story-import.store'), [
            'token' => $token, 'chapters' => [0], 'permission_confirmed' => '1',
            'cover_url' => 'https://8.8.4.4/never-fetched.png',
            'cover_image' => UploadedFile::fake()->image('cover.png'),
        ])->assertSessionHasNoErrors();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'never-fetched'));
    expect(Story::query()->firstOrFail()->cover_image)->toStartWith('covers/');
});

test('a cover url must be a real image on a public host and failures do not create the story', function (string $url, array $response) {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Storage::fake('public');
    Cache::flush();

    $admin = importDetailsAdmin();
    $token = urlPreviewToken($admin);
    Http::fake(['https://8.8.4.4/*' => Http::response($response['body'], 200, ['Content-Type' => $response['type']])]);

    $this->actingAs($admin)
        ->post(route('admin.story-import.store'), [
            'token' => $token, 'chapters' => [0], 'permission_confirmed' => '1', 'cover_url' => $url,
        ])->assertSessionHasErrors('cover_url');

    expect(Story::query()->count())->toBe(0);
})->with([
    'html instead of image' => ['https://8.8.4.4/cover.png', ['body' => '<html>nope</html>', 'type' => 'text/html']],
    'svg disguised as png' => ['https://8.8.4.4/cover.png', ['body' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'type' => 'image/png']],
    'internal address' => ['https://127.0.0.1/cover.png', ['body' => 'x', 'type' => 'image/png']],
    'plain http' => ['http://8.8.4.4/cover.png', ['body' => 'x', 'type' => 'image/png']],
]);

test('the cover host is not restricted by the import allowlist', function () {
    config(['story_import.require_allowlist' => true, 'story_import.sources' => ['8.8.8.8' => []]]);
    Http::fake(['https://8.8.4.4/cover.png' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);

    expect(app(StoryUrlImportService::class)->fetchCover('https://8.8.4.4/cover.png')['extension'])->toBe('png');
});

test('file chapters are split on chapter headings and roman numerals are understood', function () {
    $path = tempnam(sys_get_temp_dir(), 'imp').'.txt';
    file_put_contents($path, "Lời tựa bỏ qua\n\nChương 1: Mở đầu\nĐoạn một.\n\nĐoạn hai.\nChương II - Tiếp\nNội dung hai.\nCHAPTER 3\nNội dung ba.\n");

    $chapters = app(StoryFileImportService::class)->chapters($path, 'txt');

    expect(array_column($chapters, 'title'))->toBe(['Chương 1: Mở đầu', 'Chương II - Tiếp', 'CHAPTER 3'])
        ->and(array_column($chapters, 'number'))->toBe([1, 2, 3])
        ->and($chapters[0]['content'])->toContain('Đoạn một.')->toContain('Đoạn hai.')
        ->and($chapters[0]['content'])->not->toContain('Lời tựa');
});

test('a text file without headings becomes a single chapter and html is escaped', function () {
    $path = tempnam(sys_get_temp_dir(), 'imp').'.txt';
    file_put_contents($path, "Dòng <script>alert(1)</script>\nDòng hai");

    $service = app(StoryFileImportService::class);
    $chapters = $service->chapters($path, 'txt');

    expect($chapters)->toHaveCount(1)
        ->and($service->toHtml($chapters[0]['content']))->toBe("<p>Dòng &lt;script&gt;alert(1)&lt;/script&gt;</p>\n<p>Dòng hai</p>");
});

test('zip entries are ordered naturally, titled from their first line or file name', function () {
    $zip = makeZip([
        '10.txt' => "Chương 10: Mười\nNội dung mười",
        '2.txt' => 'Nội dung hai không có tiêu đề',
        '1_Khởi đầu.txt' => 'Nội dung một',
        '__MACOSX/._1.txt' => 'bỏ qua',
        'ghi-chu.md' => 'không phải txt',
    ]);

    $chapters = app(StoryFileImportService::class)->chapters($zip, 'zip');

    expect(array_column($chapters, 'number'))->toBe([1, 2, 10])
        ->and($chapters[0]['title'])->toBe('Khởi đầu')
        ->and($chapters[2]['title'])->toBe('Chương 10: Mười');
});

test('duplicate or missing chapter numbers fall back to sequential numbering', function () {
    $zip = makeZip(['a.txt' => 'Một', 'b.txt' => 'Hai']);

    expect(array_column(app(StoryFileImportService::class)->chapters($zip, 'zip'), 'number'))->toBe([1, 2]);
});

test('files that are not utf-8, empty, too large or unsupported are rejected', function () {
    $service = app(StoryFileImportService::class);

    $latin = tempnam(sys_get_temp_dir(), 'imp').'.txt';
    file_put_contents($latin, "Ch\xFAng\xE0");
    expect(fn () => $service->chapters($latin, 'txt'))->toThrow(RuntimeException::class, 'UTF-8');

    $empty = tempnam(sys_get_temp_dir(), 'imp').'.txt';
    file_put_contents($empty, "   \n");
    expect(fn () => $service->chapters($empty, 'txt'))->toThrow(RuntimeException::class, 'Không tìm thấy');

    $big = makeZip(['1.txt' => str_repeat('a', StoryFileImportService::MAX_ENTRY_BYTES + 1)]);
    expect(fn () => $service->chapters($big, 'zip'))->toThrow(RuntimeException::class, '2 MB');

    expect(fn () => $service->chapters($empty, 'exe'))->toThrow(RuntimeException::class, '.txt');
});

test('too many chapters in one file are rejected', function () {
    $files = [];
    foreach (range(1, StoryFileImportService::MAX_CHAPTERS + 1) as $i) {
        $files[sprintf('%04d.txt', $i)] = 'x';
    }

    expect(fn () => app(StoryFileImportService::class)->chapters(makeZip($files), 'zip'))
        ->toThrow(RuntimeException::class, 'quá');
});

test('admin uploads a text file, edits the preview and imports unpublished draft chapters', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    Storage::fake('public');
    Cache::flush();

    $admin = importDetailsAdmin();
    $category = Category::factory()->create();
    $file = UploadedFile::fake()->createWithContent('Truyện thử từ file.txt', "Chương 1: Một\nNội dung một.\nChương 2: Hai\nNội dung hai <b>đậm</b>.\nChương 3: Ba\nNội dung ba.");

    $response = $this->actingAs($admin)->post(route('admin.story-import.file'), ['file' => $file]);
    $response->assertSessionHasNoErrors()->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $token = $query['preview'];

    $this->actingAs($admin)->get(route('admin.story-import.index', ['preview' => $token]))
        ->assertOk()
        ->assertSee('Truyện thử từ file')
        ->assertSee('Chương 2: Hai')
        ->assertSee(route('admin.story-import.file-store'), false);

    $this->actingAs($admin)
        ->post(route('admin.story-import.file-store'), [
            'token' => $token,
            'chapters' => [0, 1],
            'permission_confirmed' => '1',
            'title' => 'Tên sau khi sửa',
            'author_name' => 'Người viết',
            'description' => 'Mô tả',
            'status' => 'ongoing',
            'categories' => [$category->id],
            'tags' => 'từ file',
            'cover_image' => UploadedFile::fake()->image('cover.png'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.story-import.index'))->assertSessionHas('success');

    $story = Story::query()->where('title', 'Tên sau khi sửa')->firstOrFail();
    $chapters = $story->chapters()->orderBy('chapter_number')->get();

    expect($story->status_publish)->toBeFalse()
        ->and($story->source_hash)->toBeNull()
        ->and($story->categories()->count())->toBe(1)
        ->and($story->tags()->pluck('name')->all())->toBe(['từ file'])
        ->and($story->cover_image)->toStartWith('covers/')
        ->and($chapters->pluck('title')->all())->toBe(['Chương 1: Một', 'Chương 2: Hai'])
        ->and($chapters->pluck('status')->unique()->all())->toBe([false])
        ->and($chapters[1]->content)->toContain('&lt;b&gt;đậm&lt;/b&gt;')->not->toContain('<b>');

    // The uploaded file is removed after the import and the preview cannot be reused.
    expect(Storage::disk('local')->allFiles('story-imports'))->toBe([]);

    $this->actingAs($admin)
        ->post(route('admin.story-import.file-store'), ['token' => $token, 'chapters' => [0], 'permission_confirmed' => '1', 'title' => 'Dùng lại', 'status' => 'ongoing'])
        ->assertSessionHasErrors('file');
    expect(Chapter::query()->count())->toBe(2);
});

test('admin imports a zip with one chapter per file', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    Cache::flush();

    $admin = importDetailsAdmin();
    $zip = makeZip(['001.txt' => "Chương 1\nMột", '002.txt' => "Chương 2\nHai"]);
    $upload = new UploadedFile($zip, 'bo-truyen.zip', 'application/zip', null, true);

    $response = $this->actingAs($admin)->post(route('admin.story-import.file'), ['file' => $upload]);
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->actingAs($admin)
        ->post(route('admin.story-import.file-store'), ['token' => $query['preview'], 'chapters' => [0, 1], 'permission_confirmed' => '1'])
        ->assertSessionHasNoErrors();

    $story = Story::query()->firstOrFail();
    expect($story->title)->toBe('bo-truyen')
        ->and($story->chapters()->count())->toBe(2);
});

test('file import rejects bad uploads, other admins previews and url previews', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    Cache::flush();

    $admin = importDetailsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.story-import.file'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('file');

    $response = $this->actingAs($admin)->post(route('admin.story-import.file'), [
        'file' => UploadedFile::fake()->createWithContent('a.txt', "Chương 1\nNội dung"),
    ]);
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    $other = importDetailsAdmin();
    $this->actingAs($other)
        ->post(route('admin.story-import.file-store'), ['token' => $query['preview'], 'chapters' => [0], 'permission_confirmed' => '1'])
        ->assertForbidden();

    // A URL-import preview cannot be used with the file endpoint, nor the other way round.
    $urlToken = urlPreviewToken($admin);
    $this->actingAs($admin)
        ->post(route('admin.story-import.file-store'), ['token' => $urlToken, 'chapters' => [0], 'permission_confirmed' => '1'])
        ->assertSessionHasErrors('file');

    $this->actingAs($admin)
        ->post(route('admin.story-import.store'), ['token' => $query['preview'], 'chapters' => [0], 'permission_confirmed' => '1'])
        ->assertStatus(422);

    expect(Story::query()->count())->toBe(0);
});

test('validation errors on the details keep the preview so nothing is created', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Bus::fake();
    Cache::flush();

    $admin = importDetailsAdmin();
    $token = urlPreviewToken($admin);

    $this->actingAs($admin)
        ->post(route('admin.story-import.store'), [
            'token' => $token, 'chapters' => [0], 'permission_confirmed' => '1',
            'title' => '<script>x</script>', 'status' => 'weird', 'tags' => implode(',', range(1, 11)),
        ])->assertSessionHasErrors(['title', 'status', 'tags']);

    expect(Story::query()->count())->toBe(0)
        ->and(Tag::query()->count())->toBe(0)
        ->and(Cache::has('story-url-import:'.$token))->toBeTrue();
});

test('navigation links such as "Chương mới" are not mistaken for chapters', function () {
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="T"></head><body>'
            .'<a href="/chuong-moi">Chương mới</a><a href="/c/12">Chương 12 tân</a><a href="/c/iv">Chương IV</a><a href="/c/m">Chương mở đầu</a></body></html>'),
    ]);

    $chapters = app(StoryUrlImportService::class)->preview('https://8.8.8.8/story')['chapters'];

    expect(array_column($chapters, 'title'))->toBe(['Chương 12 tân', 'Chương IV']);
});
