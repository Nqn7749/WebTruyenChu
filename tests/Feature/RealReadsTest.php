<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function publishedStoryWithChapter(array $storyAttributes = [], array $chapterAttributes = []): array
{
    $story = Story::factory()->create(array_merge([
        'status_publish' => true,
        'views' => 0,
    ], $storyAttributes));

    $chapter = Chapter::factory()->create(array_merge([
        'story_id' => $story->id,
        'status' => true,
        'views' => 0,
    ], $chapterAttributes));

    return [$story, $chapter];
}

// Trình duyệt thật: request không có User-Agent bị coi là bot nên
// không tính view/lượt đọc thật (R7). Các test đếm view/read phải
// gửi UA này để giả lập trình duyệt thật.
function realBrowserUserAgent(): string
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
}

test('opening a chapter counts one view for the chapter and the story, reopening does not count again', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $this->withHeaders(['User-Agent' => realBrowserUserAgent()]);

    $this->get(route('chapters.show', [$story, $chapter]))->assertOk();

    expect($chapter->fresh()->views)->toBe(1)
        ->and($story->fresh()->views)->toBe(1);

    // Mở lại trong cùng session: không đếm thêm.
    $this->get(route('chapters.show', [$story, $chapter]))->assertOk();

    expect($chapter->fresh()->views)->toBe(1)
        ->and($story->fresh()->views)->toBe(1);
});

test('a bot user agent does not count a view', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
        ->get(route('chapters.show', [$story, $chapter]))
        ->assertOk();

    expect($chapter->fresh()->views)->toBe(0)
        ->and($story->fresh()->views)->toBe(0);
});

test('views are throttled per logged-in user, not per IP', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $this->withHeaders(['User-Agent' => realBrowserUserAgent()]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Hai user khác nhau, cùng một IP (môi trường test chỉ có 127.0.0.1):
    // trước đây chỉ người đầu tiên được tính vì throttle theo IP.
    $this->actingAs($userA)
        ->get(route('chapters.show', [$story, $chapter]))
        ->assertOk();

    expect($chapter->fresh()->views)->toBe(1);

    // Giả lập trình duyệt khác (session mới) cho user B.
    session()->invalidate();

    $this->actingAs($userB)
        ->get(route('chapters.show', [$story, $chapter]))
        ->assertOk();

    expect($chapter->fresh()->views)->toBe(2)
        ->and($story->fresh()->views)->toBe(2);
});

test('counting a view does not change the story updated_at', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $this->withHeaders(['User-Agent' => realBrowserUserAgent()]);

    DB::table('stories')->where('id', $story->id)->update(['updated_at' => '2026-01-01 00:00:00']);

    $this->get(route('chapters.show', [$story, $chapter]))->assertOk();

    expect($story->fresh()->views)->toBe(1)
        ->and($story->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00');
});

test('a real read counts once per chapter per day for a guest', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $this->withHeaders(['User-Agent' => realBrowserUserAgent()]);

    $this->postJson(route('chapters.record-read', [$story, $chapter]))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($chapter->fresh()->reads_count)->toBe(1)
        ->and($story->fresh()->reads_count)->toBe(1);

    // Gọi lại trong ngày: chống trùng, không đếm thêm.
    $this->postJson(route('chapters.record-read', [$story, $chapter]))
        ->assertOk()
        ->assertJson(['status' => 'already_recorded']);

    expect($chapter->fresh()->reads_count)->toBe(1)
        ->and($story->fresh()->reads_count)->toBe(1);
});

test('a real read is ignored for a bot user agent', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $this->withHeaders(['User-Agent' => 'facebookexternalhit/1.1'])
        ->postJson(route('chapters.record-read', [$story, $chapter]))
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    expect($chapter->fresh()->reads_count)->toBe(0)
        ->and($story->fresh()->reads_count)->toBe(0);
});

test('a real read returns 404 for an unpublished story or chapter', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $otherStory = Story::factory()->create(['status_publish' => true]);

    // Chương không thuộc truyện trên URL.
    $this->postJson(route('chapters.record-read', [$otherStory, $chapter]))
        ->assertNotFound();

    // Truyện chưa publish.
    $story->forceFill(['status_publish' => false])->save();
    $this->postJson(route('chapters.record-read', [$story, $chapter]))
        ->assertNotFound();

    expect($chapter->fresh()->reads_count)->toBe(0);
});

test('recording a real read does not change the story updated_at', function () {
    [$story, $chapter] = publishedStoryWithChapter();

    $this->withHeaders(['User-Agent' => realBrowserUserAgent()]);

    DB::table('stories')->where('id', $story->id)->update(['updated_at' => '2026-01-01 00:00:00']);

    $this->postJson(route('chapters.record-read', [$story, $chapter]))->assertOk();

    expect($story->fresh()->reads_count)->toBe(1)
        ->and($story->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00');
});

test('hot stories are ordered by real reads first, views as tiebreaker', function () {
    $lowReadsHighViews = Story::factory()->create(['status_publish' => true, 'views' => 100]);
    $lowReadsHighViews->forceFill(['is_hot' => true, 'reads_count' => 5])->save();

    $highReadsNoViews = Story::factory()->create(['status_publish' => true, 'views' => 0]);
    $highReadsNoViews->forceFill(['is_hot' => true, 'reads_count' => 10])->save();

    $highReadsHighViews = Story::factory()->create(['status_publish' => true, 'views' => 50]);
    $highReadsHighViews->forceFill(['is_hot' => true, 'reads_count' => 10])->save();

    $ordered = Story::published()->hot()->pluck('id')->all();

    expect($ordered)->toBe([
        $highReadsHighViews->id,
        $highReadsNoViews->id,
        $lowReadsHighViews->id,
    ]);
});

test('the story page shows the real reads count', function () {
    [$story] = publishedStoryWithChapter();
    $story->forceFill(['reads_count' => 42])->save();

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee('<span>Lượt đọc thật</span><strong>42</strong>', false);
});
