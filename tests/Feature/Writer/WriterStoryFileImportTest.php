<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function writerUploadPreviewToken($test, User $author, string $name = 'Truyện của tôi.txt', ?string $content = null): string
{
    $file = UploadedFile::fake()->createWithContent($name, $content ?? "Chương 1: Mở đầu\nNội dung một.\nChương 2: Tiếp\nNội dung hai.\nChương 3: Kết\nNội dung ba.");

    $response = $test->actingAs($author)->post(route('writer.stories.import.upload'), ['file' => $file]);
    $response->assertSessionHasNoErrors()->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return $query['preview'];
}

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    Storage::fake('public');
    Cache::flush();
});

test('an author sees the import page and the entry point on their stories list', function () {
    $author = User::factory()->create(['status' => true]);

    $this->actingAs($author)->get(route('writer.stories.index'))
        ->assertOk()->assertSee(route('writer.stories.import'), false);

    $this->actingAs($author)->get(route('writer.stories.import'))
        ->assertOk()->assertSee('Đăng truyện từ tệp');
});

test('guests cannot use the file import', function () {
    $this->get(route('writer.stories.import'))->assertRedirect(route('login'));
    $this->post(route('writer.stories.import.upload'))->assertRedirect(route('login'));
    $this->post(route('writer.stories.import.store'))->assertRedirect(route('login'));
});

test('an author uploads a file, reviews the chapters and publishes the story from it', function () {
    $author = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();
    $token = writerUploadPreviewToken($this, $author);

    $this->actingAs($author)->get(route('writer.stories.import', ['preview' => $token]))
        ->assertOk()->assertSee('Chương 2: Tiếp')->assertSee('Truyện của tôi');

    $this->actingAs($author)->post(route('writer.stories.import.store'), [
        'token' => $token,
        'chapters' => [0, 2],
        'authorship_confirmed' => '1',
        'publish_chapters' => '1',
        'title' => 'Truyện đã đăng',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu',
        'status' => 'ongoing',
        'categories' => [$category->id],
        'tags' => 'tự đăng, từ tệp',
        'cover_image' => UploadedFile::fake()->image('cover.png'),
    ])->assertSessionHasNoErrors()->assertRedirect(route('writer.stories.index'))->assertSessionHas('success');

    $story = Story::query()->where('title', 'Truyện đã đăng')->firstOrFail();
    $chapters = $story->chapters()->orderBy('chapter_number')->get();

    expect($story->user_id)->toBe($author->id)
        ->and($story->status_publish)->toBeTrue()
        ->and($story->is_featured)->toBeFalse()
        ->and($story->author_name)->toBe('Bút danh')
        ->and($story->categories()->count())->toBe(1)
        ->and($story->tags()->pluck('name')->sort()->values()->all())->toBe(['từ tệp', 'tự đăng'])
        ->and($story->cover_image)->toStartWith('covers/')
        ->and($chapters->pluck('title')->all())->toBe(['Chương 1: Mở đầu', 'Chương 3: Kết'])
        ->and($chapters->pluck('status')->unique()->all())->toBe([true])
        ->and($story->fresh()->chapter_count)->toBe(2);

    Storage::disk('public')->assertExists($story->cover_image);
    expect(Storage::disk('local')->allFiles('story-imports'))->toBe([]);

    // The public can now read the first imported chapter.
    $this->get(route('chapters.show', [$story, $chapters[0]->slug]))->assertOk()->assertSee('Nội dung một.');
});

test('chapters are saved as drafts unless the author chooses to publish them', function () {
    $author = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();
    $token = writerUploadPreviewToken($this, $author);

    $this->actingAs($author)->post(route('writer.stories.import.store'), [
        'token' => $token, 'chapters' => [0, 1, 2], 'authorship_confirmed' => '1', 'publish_chapters' => '0',
        'title' => 'Bản nháp', 'status' => 'ongoing', 'categories' => [$category->id],
    ])->assertSessionHasNoErrors();

    $story = Story::query()->where('title', 'Bản nháp')->firstOrFail();

    expect($story->chapters()->pluck('status')->unique()->all())->toBe([false])
        ->and($story->chapters()->count())->toBe(3);
});

test('an author can import a zip with one chapter per file and html in the text is escaped', function () {
    $author = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    $path = tempnam(sys_get_temp_dir(), 'wri').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('001.txt', "Chương 1\nXin <script>alert(1)</script> chào");
    $zip->addFromString('002.txt', "Chương 2\nHai");
    $zip->close();

    $response = $this->actingAs($author)->post(route('writer.stories.import.upload'), [
        'file' => new UploadedFile($path, 'bo.zip', 'application/zip', null, true),
    ]);
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->actingAs($author)->post(route('writer.stories.import.store'), [
        'token' => $query['preview'], 'chapters' => [0, 1], 'authorship_confirmed' => '1', 'publish_chapters' => '1',
        'title' => 'Từ zip', 'status' => 'ongoing', 'categories' => [$category->id],
    ])->assertSessionHasNoErrors();

    $first = Chapter::query()->where('chapter_number', 1)->firstOrFail();
    expect($first->content)->toContain('&lt;script&gt;')->not->toContain('<script>');
});

test('the story needs a category and a confirmation of authorship and nothing is created without them', function () {
    $author = User::factory()->create(['status' => true]);
    $token = writerUploadPreviewToken($this, $author);

    $this->actingAs($author)->post(route('writer.stories.import.store'), [
        'token' => $token, 'chapters' => [0], 'title' => 'Thiếu', 'status' => 'ongoing',
    ])->assertSessionHasErrors(['categories', 'authorship_confirmed']);

    $this->actingAs($author)->post(route('writer.stories.import.store'), [
        'token' => $token, 'chapters' => [0], 'authorship_confirmed' => '1', 'categories' => [Category::factory()->create()->id],
        'title' => '<b>x</b>', 'status' => 'weird', 'tags' => implode(',', range(1, 11)),
    ])->assertSessionHasErrors(['title', 'status', 'tags']);

    expect(Story::query()->count())->toBe(0)->and(Tag::query()->count())->toBe(0);
});

test('bad uploads are rejected with a clear message', function () {
    $author = User::factory()->create(['status' => true]);

    $this->actingAs($author)->post(route('writer.stories.import.upload'), ['file' => UploadedFile::fake()->create('x.exe', 5)])
        ->assertSessionHasErrors('file');

    $this->actingAs($author)->post(route('writer.stories.import.upload'), [
        'file' => UploadedFile::fake()->createWithContent('latin.txt', "Ch\xFAng\xE0"),
    ])->assertSessionHasErrors('file');

    $this->actingAs($author)->post(route('writer.stories.import.upload'), [
        'file' => UploadedFile::fake()->create('big.txt', 10241),
    ])->assertSessionHasErrors('file');
});

test('authors can import at most two hundred chapters from one file', function () {
    $author = User::factory()->create(['status' => true]);
    $text = '';
    foreach (range(1, 201) as $i) {
        $text .= "Chương $i\nNội dung $i\n";
    }

    $this->actingAs($author)->post(route('writer.stories.import.upload'), [
        'file' => UploadedFile::fake()->createWithContent('dai.txt', $text),
    ])->assertSessionHasErrors('file');
});

test('a preview belongs to the author who uploaded it', function () {
    $author = User::factory()->create(['status' => true]);
    $other = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();
    $token = writerUploadPreviewToken($this, $author);

    $this->actingAs($other)->get(route('writer.stories.import', ['preview' => $token]))
        ->assertOk()->assertDontSee('Chương 2: Tiếp');

    $this->actingAs($other)->post(route('writer.stories.import.store'), [
        'token' => $token, 'chapters' => [0], 'authorship_confirmed' => '1', 'title' => 'Trộm', 'status' => 'ongoing', 'categories' => [$category->id],
    ])->assertSessionHasErrors('file');

    expect(Story::query()->count())->toBe(0);
});

test('a used preview cannot be imported twice', function () {
    $author = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();
    $token = writerUploadPreviewToken($this, $author);
    $payload = ['token' => $token, 'chapters' => [0], 'authorship_confirmed' => '1', 'title' => 'Một lần', 'status' => 'ongoing', 'categories' => [$category->id]];

    $this->actingAs($author)->post(route('writer.stories.import.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($author)->post(route('writer.stories.import.store'), $payload)->assertSessionHasErrors('file');

    expect(Story::query()->count())->toBe(1);
});
