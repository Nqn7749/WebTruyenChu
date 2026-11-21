<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryStatsService;
use Illuminate\Support\Facades\Notification;

test('unpublishing a chapter decreases the story comment count and republishing restores it', function () {
    Notification::fake();

    $author = User::factory()->create(['status' => true]);
    $commenter = User::factory()->create(['status' => true]);
    $story = Story::factory()->create([
        'user_id' => $author->id,
        'status_publish' => true,
        'comment_count' => 0,
    ]);
    $chapter = Chapter::factory()->create([
        'story_id' => $story->id,
        'status' => true,
    ]);

    Comment::factory()->count(2)->create([
        'user_id' => $commenter->id,
        'story_id' => $story->id,
        'chapter_id' => $chapter->id,
        'parent_id' => null,
        'is_hidden' => false,
    ]);

    // Đồng bộ số đếm ban đầu từ source: 2 bình luận đang hiển thị.
    app(StoryStatsService::class)->refreshStories([$story->id]);
    expect($story->fresh()->comment_count)->toBe(2);

    // Gỡ công khai qua writer update: bình luận của chương không còn
    // hiển thị nên comment_count của truyện phải về 0 (R7).
    $this->actingAs($author)
        ->put(route('writer.chapters.update', $chapter), [
            'chapter_number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'content' => $chapter->content,
            'status' => 0,
        ])->assertRedirect();

    expect($chapter->fresh()->status)->toBeFalse()
        ->and($story->fresh()->comment_count)->toBe(0);

    // Đăng lại: bình luận hiện lại nên số đếm trở về 2.
    $this->actingAs($author)
        ->put(route('writer.chapters.update', $chapter), [
            'chapter_number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'content' => $chapter->content,
            'status' => 1,
        ])->assertRedirect();

    expect($chapter->fresh()->status)->toBeTrue()
        ->and($story->fresh()->comment_count)->toBe(2);
});

test('unpublishing a chapter via admin update also decreases the story comment count', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $commenter = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['status_publish' => true, 'comment_count' => 0]);
    $chapter = Chapter::factory()->create(['story_id' => $story->id, 'status' => true]);

    Comment::factory()->create([
        'user_id' => $commenter->id,
        'story_id' => $story->id,
        'chapter_id' => $chapter->id,
        'parent_id' => null,
        'is_hidden' => false,
    ]);

    app(StoryStatsService::class)->refreshStories([$story->id]);
    expect($story->fresh()->comment_count)->toBe(1);

    $this->actingAs($admin)
        ->put(route('admin.chapters.update', $chapter), [
            'chapter_number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'content' => $chapter->content,
            'status' => 0,
        ])->assertRedirect();

    expect($chapter->fresh()->status)->toBeFalse()
        ->and($story->fresh()->comment_count)->toBe(0);
});

test('decrementing a comment count by zero or a negative amount leaves it unchanged', function () {
    $story = Story::factory()->create(['comment_count' => 5]);

    // Trước đây trừ 0 không đổi dòng nào nên MySQL báo 0 affected
    // rows, câu thứ hai set nhầm comment_count về 0 (R7).
    StoryStatsService::decrementCommentCount('stories', $story->id, 0);

    expect($story->fresh()->comment_count)->toBe(5);

    StoryStatsService::decrementCommentCount('stories', $story->id, -3);

    expect($story->fresh()->comment_count)->toBe(5);
});

test('opening a chapter without a user agent does not count a view', function () {
    $story = Story::factory()->create(['status_publish' => true, 'views' => 0]);
    $chapter = Chapter::factory()->create([
        'story_id' => $story->id,
        'status' => true,
        'views' => 0,
    ]);

    // Test client mặc định gửi User-Agent "Symfony" nên phải set rỗng
    // tường minh để giả lập request không có UA: request này là bot (R7).
    $this->withHeaders(['User-Agent' => ''])
        ->get(route('chapters.show', [$story, $chapter]))
        ->assertOk();

    expect($chapter->fresh()->views)->toBe(0)
        ->and($story->fresh()->views)->toBe(0);
});

test('opening a chapter with a browser user agent counts a view', function () {
    $story = Story::factory()->create(['status_publish' => true, 'views' => 0]);
    $chapter = Chapter::factory()->create([
        'story_id' => $story->id,
        'status' => true,
        'views' => 0,
    ]);

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'])
        ->get(route('chapters.show', [$story, $chapter]))
        ->assertOk();

    expect($chapter->fresh()->views)->toBe(1)
        ->and($story->fresh()->views)->toBe(1);
});

test('a real read without a user agent is ignored', function () {
    $story = Story::factory()->create(['status_publish' => true, 'reads_count' => 0]);
    $chapter = Chapter::factory()->create([
        'story_id' => $story->id,
        'status' => true,
        'reads_count' => 0,
    ]);

    // Như test view ở trên: set UA rỗng tường minh (mặc định client
    // gửi "Symfony" nên không set thì request không bị tính là bot).
    $this->withHeaders(['User-Agent' => ''])
        ->postJson(route('chapters.record-read', [$story, $chapter]))
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    expect($chapter->fresh()->reads_count)->toBe(0)
        ->and($story->fresh()->reads_count)->toBe(0);
});
