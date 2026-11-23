<?php

declare(strict_types=1);

use App\Jobs\SendBulkChapterNotifications;
use App\Models\Chapter;
use App\Models\Favorite;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function chapterImportToken($test, User $author, Story $story, ?string $content = null): string
{
    $file = UploadedFile::fake()->createWithContent('chuong.txt', $content ?? "Chương 1: Một\nNội dung một.\nChương 2: Hai\nNội dung hai.\nChương 3: Ba\nNội dung ba.");

    $response = $test->actingAs($author)->post(route('writer.stories.chapters.import.upload', $story), ['file' => $file]);
    $response->assertSessionHasNoErrors()->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return $query['preview'];
}

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    Cache::flush();
    $this->author = User::factory()->create(['status' => true]);
    $this->story = Story::factory()->create(['user_id' => $this->author->id, 'status_publish' => true]);
});

test('the chapter list offers adding chapters from a file and the import page loads', function () {
    $this->actingAs($this->author)->get(route('writer.stories.chapters.index', $this->story))
        ->assertOk()->assertSee(route('writer.stories.chapters.import', $this->story), false);

    $this->actingAs($this->author)->get(route('writer.stories.chapters.import', $this->story))
        ->assertOk()->assertSee('Thêm chương từ tệp');
});

test('chapters are added to an existing story as drafts using the numbers in the file', function () {
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->get(route('writer.stories.chapters.import', [$this->story, 'preview' => $token]))
        ->assertOk()->assertSee('Chương 2: Hai');

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0, 1, 2], 'numbering' => 'file', 'publish_chapters' => '0',
    ])->assertSessionHasNoErrors()->assertRedirect(route('writer.stories.chapters.index', $this->story))->assertSessionHas('success');

    $chapters = $this->story->chapters()->orderBy('chapter_number')->get();

    expect($chapters->pluck('chapter_number')->all())->toBe([1, 2, 3])
        ->and($chapters->pluck('title')->all())->toBe(['Chương 1: Một', 'Chương 2: Hai', 'Chương 3: Ba'])
        ->and($chapters->pluck('status')->unique()->all())->toBe([false])
        ->and($chapters[0]->content)->toContain('<p>Nội dung một.</p>')
        ->and(Storage::disk('local')->allFiles('story-imports'))->toBe([]);
});

test('append numbering continues after the last existing chapter', function () {
    Chapter::factory()->for($this->story)->create(['chapter_number' => 7, 'status' => true]);
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0, 1], 'numbering' => 'append',
    ])->assertSessionHasNoErrors();

    expect($this->story->chapters()->orderBy('chapter_number')->pluck('chapter_number')->all())->toBe([7, 8, 9]);
});

test('file numbering refuses numbers that already exist and creates nothing', function () {
    Chapter::factory()->for($this->story)->create(['chapter_number' => 2, 'status' => true]);
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->get(route('writer.stories.chapters.import', [$this->story, 'preview' => $token]))
        ->assertOk()->assertSee('đã có số 2')->assertSee('trùng số');

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0, 1, 2], 'numbering' => 'file',
    ])->assertSessionHasErrors('chapters');

    expect($this->story->chapters()->count())->toBe(1);

    // Leaving the clashing chapter out works.
    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0, 2], 'numbering' => 'file',
    ])->assertSessionHasNoErrors();

    expect($this->story->chapters()->orderBy('chapter_number')->pluck('chapter_number')->all())->toBe([1, 2, 3]);
});

test('a soft-deleted chapter number can be reused', function () {
    $deleted = Chapter::factory()->for($this->story)->create(['chapter_number' => 1, 'status' => true]);
    $deleted->delete();
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0], 'numbering' => 'file',
    ])->assertSessionHasNoErrors();

    expect($this->story->chapters()->pluck('chapter_number')->all())->toBe([1]);
});

test('duplicate numbers inside the file need append numbering', function () {
    $token = chapterImportToken($this, $this->author, $this->story, "Chương 1: A\nMột.\nChương 1: B\nHai.");

    // The file service renumbers duplicates sequentially, so both chapters import with file numbering.
    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0, 1], 'numbering' => 'file',
    ])->assertSessionHasNoErrors();

    expect($this->story->chapters()->orderBy('chapter_number')->pluck('chapter_number')->all())->toBe([1, 2]);
});

test('publishing the imported chapters notifies followers in the background', function () {
    Bus::fake([SendBulkChapterNotifications::class]);
    Favorite::create(['user_id' => User::factory()->create()->id, 'story_id' => $this->story->id, 'notify_new_chapter' => true]);
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0, 1], 'numbering' => 'file', 'publish_chapters' => '1',
    ])->assertSessionHasNoErrors();

    expect($this->story->chapters()->pluck('status')->unique()->all())->toBe([true])
        ->and($this->story->fresh()->chapter_count)->toBe(2);

    Bus::assertDispatched(SendBulkChapterNotifications::class, fn ($job) => count($job->chapterIds) === 2);
});

test('draft imports send no notifications', function () {
    Bus::fake([SendBulkChapterNotifications::class]);
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0], 'numbering' => 'file',
    ])->assertSessionHasNoErrors();

    Bus::assertNotDispatched(SendBulkChapterNotifications::class);
});

test('an author cannot import chapters into someone elses story', function () {
    $other = User::factory()->create(['status' => true]);
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($other)->get(route('writer.stories.chapters.import', $this->story))->assertForbidden();
    $this->actingAs($other)->post(route('writer.stories.chapters.import.upload', $this->story), [
        'file' => UploadedFile::fake()->createWithContent('a.txt', 'Chương 1\nx'),
    ])->assertForbidden();
    $this->actingAs($other)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0], 'numbering' => 'file',
    ])->assertForbidden();

    expect($this->story->chapters()->count())->toBe(0);
});

test('a preview cannot be used for another story or twice', function () {
    $otherStory = Story::factory()->create(['user_id' => $this->author->id, 'status_publish' => true]);
    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $otherStory), [
        'token' => $token, 'chapters' => [0], 'numbering' => 'file',
    ])->assertSessionHasErrors('file');

    $payload = ['token' => $token, 'chapters' => [0], 'numbering' => 'file'];
    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), $payload)->assertSessionHasNoErrors();
    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), $payload)->assertSessionHasErrors('file');

    expect($otherStory->chapters()->count())->toBe(0)->and($this->story->chapters()->count())->toBe(1);
});

test('bad files and missing choices are rejected with clear errors', function () {
    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.upload', $this->story), [
        'file' => UploadedFile::fake()->create('x.exe', 5),
    ])->assertSessionHasErrors('file');

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.upload', $this->story), [
        'file' => UploadedFile::fake()->createWithContent('latin.txt', "Ch\xFAng\xE0"),
    ])->assertSessionHasErrors('file');

    $token = chapterImportToken($this, $this->author, $this->story);

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'numbering' => 'file',
    ])->assertSessionHasErrors('chapters');

    $this->actingAs($this->author)->post(route('writer.stories.chapters.import.store', $this->story), [
        'token' => $token, 'chapters' => [0],
    ])->assertSessionHasErrors('numbering');

    expect($this->story->chapters()->count())->toBe(0);
});

test('guests are sent to login', function () {
    $this->get(route('writer.stories.chapters.import', $this->story))->assertRedirect(route('login'));
});
