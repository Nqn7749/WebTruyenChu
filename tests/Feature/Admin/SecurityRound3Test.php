<?php

use App\Jobs\SendBulkChapterNotifications;
use App\Jobs\SendNewChapterNotifications;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Favorite;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use App\Services\StoryStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('NM-1: app:create-admin creates a verified administrator account', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Admin name', 'Quản Trị')
        ->expectsQuestion('Admin username', 'quantri')
        ->expectsQuestion('Admin email', 'quantri@example.com')
        ->expectsQuestion('Admin password', 'MatKhau!123456')
        ->assertExitCode(0);

    $user = User::query()->where('email', 'quantri@example.com')->firstOrFail();

    expect($user->role?->slug)->toBe('admin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->status)->toBeTrue();
});

test('NM-2: registration rejects a name longer than the 100 character column', function () {
    $this->post(route('register.store'), [
        'name' => str_repeat('A', 101),
        'username' => 'nguoidung',
        'email' => 'nguoidung@example.com',
        'password' => 'Password1234',
        'password_confirmation' => 'Password1234',
    ])->assertSessionHasErrors('name');

    expect(User::query()->where('email', 'nguoidung@example.com')->exists())->toBeFalse();
});

test('NM-3: a story page loads at most 20 replies per comment', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $parent = Comment::factory()->create([
        'story_id' => $story->id,
        'chapter_id' => null,
        'parent_id' => null,
        'is_hidden' => false,
        'content' => 'Bình luận gốc.',
    ]);

    foreach (range(1, 25) as $i) {
        Comment::factory()->create([
            'story_id' => $story->id,
            'chapter_id' => null,
            'parent_id' => $parent->id,
            'is_hidden' => false,
            'content' => 'Reply '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee('Reply 01', false)
        ->assertSee('Reply 20', false)
        ->assertDontSee('Reply 25', false);
});

test('NM-4: deleting a comment never drives comment_count below zero', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['status_publish' => true, 'comment_count' => 0]);
    $comment = Comment::factory()->create([
        'story_id' => $story->id,
        'chapter_id' => null,
        'parent_id' => null,
        'is_hidden' => false,
        'user_id' => $author->id,
    ]);

    $this->actingAs($author)
        ->deleteJson(route('comments.destroy', $comment))
        ->assertOk();

    expect($story->fresh()->comment_count)->toBe(0);
});

test('NM-5: the search endpoint is throttled', function () {
    foreach (range(1, 30) as $attempt) {
        $this->get('/tim-kiem?q=test')->assertOk();
    }

    $this->get('/tim-kiem?q=test')->assertTooManyRequests();
});

test('N-L1: an admin can open the chapter edit page of a soft-deleted story', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $story->delete();

    $this->actingAs($admin)
        ->get('/admin/chapters/'.$chapter->id.'/edit')
        ->assertOk();
});

test('N-L2: og:url keeps the search query string', function () {
    Story::factory()->create(['status_publish' => true]);

    $this->get('/tim-kiem?q=truyen')
        ->assertOk()
        ->assertSee('<meta property="og:url" content="http://localhost/tim-kiem?q=truyen">', false);
});

test('N-L3: deleting a parent comment also removes its visible replies from comment_count', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['status_publish' => true, 'comment_count' => 3]);
    $parent = Comment::factory()->create([
        'story_id' => $story->id,
        'chapter_id' => null,
        'parent_id' => null,
        'is_hidden' => false,
        'user_id' => $author->id,
    ]);
    Comment::factory()->count(2)->create([
        'story_id' => $story->id,
        'chapter_id' => null,
        'parent_id' => $parent->id,
        'is_hidden' => false,
    ]);

    $this->actingAs($author)
        ->deleteJson(route('comments.destroy', $parent))
        ->assertOk();

    expect($story->fresh()->comment_count)->toBe(0);
});

test('N-L4: the viewed chapters session list is capped at 100 entries', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    // Trình duyệt thật: request không có User-Agent bị coi là bot nên
    // trackView trả về sớm, không ghi vào session viewed_chapters (R7).
    $this->withSession(['viewed_chapters' => range(200, 349)])
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'])
        ->get(route('chapters.show', [$story, $chapter]))
        ->assertOk();

    $viewed = session('viewed_chapters');

    expect($viewed)->toHaveCount(100)
        ->and($viewed)->toContain($chapter->id);
});

test('N-L5: republishing a story dispatches one bulk notification job for all its chapters', function () {
    Queue::fake();

    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => false]);
    $chapters = Chapter::factory()->for($story)->count(3)->create(['status' => true]);
    Chapter::factory()->for($story)->create(['status' => false]);

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-publish', $story))
        ->assertRedirect();

    expect($story->fresh()->status_publish)->toBeTrue();
    Queue::assertPushed(SendBulkChapterNotifications::class, 1);
    Queue::assertPushed(SendBulkChapterNotifications::class, fn ($job) => $job->chapterIds === $chapters->pluck('id')->sort()->values()->all());
    Queue::assertNotPushed(SendNewChapterNotifications::class);
});

test('M7: search uses the FULLTEXT index on MySQL and still finds stories by keyword', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-specific FULLTEXT search.');
    }

    Story::factory()->create([
        'title' => 'The Ember Chronicles',
        'description' => 'A journey through distant kingdoms.',
        'status_publish' => true,
    ]);

    // InnoDB publishes FULLTEXT index changes at transaction commit. The
    // Feature suite's RefreshDatabase transaction would otherwise hide the
    // newly inserted row from MATCH ... AGAINST in this integration test.
    $connection = DB::connection();
    $connection->commit();
    $connection->beginTransaction();

    $this->get('/tim-kiem?q=Ember')
        ->assertOk()
        ->assertSee('The Ember Chronicles', false);
});

test('NL-2: an unverified administrator is redirected away from the admin area', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->unverified()->create(['role_id' => $adminRole->id, 'status' => true]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect(route('verification.notice'));
});

test('NL-3: chapter notifications skip disabled users and the story author', function () {
    Notification::fake();

    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $disabledFollower = User::factory()->create(['status' => false]);
    $follower = User::factory()->create(['status' => true]);

    Favorite::create(['user_id' => $disabledFollower->id, 'story_id' => $story->id, 'notify_new_chapter' => true]);
    Favorite::create(['user_id' => $author->id, 'story_id' => $story->id, 'notify_new_chapter' => true]);
    Favorite::create(['user_id' => $follower->id, 'story_id' => $story->id, 'notify_new_chapter' => true]);

    (new SendNewChapterNotifications($chapter->id))->handle();

    Notification::assertSentTo($follower, NewChapterPublished::class);
    Notification::assertNotSentTo($disabledFollower, NewChapterPublished::class);
    Notification::assertNotSentTo($author, NewChapterPublished::class);
});

test('NL-4: recounting story stats does not touch updated_at', function () {
    $story = Story::factory()->create(['status_publish' => true]);

    DB::table('stories')->where('id', $story->id)->update(['updated_at' => '2020-01-01 00:00:00']);

    app(StoryStatsService::class)->refreshChapterStats($story->fresh());

    expect($story->fresh()->updated_at->format('Y-m-d'))->toBe('2020-01-01');
});

test('NL-5: the chapters sitemap does not crash when published_at is null', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    DB::table('chapters')->where('id', $chapter->id)->update(['published_at' => null]);

    $this->get('/sitemap-chapters-1.xml')->assertOk();
});

test('NL-6: the sitemap lists search and story list pages and all categories', function () {
    $emptyCategory = Category::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);
    $category = Category::factory()->create();
    $story->categories()->attach($category->id);

    $this->get('/sitemap-static.xml')
        ->assertOk()
        ->assertSee('/tim-kiem', false)
        ->assertSee('/danh-sach-truyen', false);

    // Lọc sitemap categories chỉ còn thể loại có truyện đã publish đã bị
    // bỏ (fix1): nó làm sitemap trống với thể loại chưa có truyện và phá
    // test SitemapCacheInvalidationTest có sẵn, nên sitemap liệt kê mọi
    // thể loại như trước.
    $this->get('/sitemap-categories.xml')
        ->assertOk()
        ->assertSee($category->slug, false)
        ->assertSee($emptyCategory->slug, false);
});
