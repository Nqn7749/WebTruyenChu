<?php

declare(strict_types=1);

use App\Models\Favorite;
use App\Models\Story;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function rankStat(Story $story, int $views, int $reads, ?string $date = null): void
{
    DB::table('story_daily_stats')->insert([
        'story_id' => $story->id,
        'stat_date' => $date ?? today()->toDateString(),
        'views' => $views,
        'reads' => $reads,
    ]);
}

function rankedTitles($response): array
{
    preg_match_all('/class="fw-semibold text-decoration-none d-block text-truncate">([^<]+)</', $response->getContent(), $matches);

    return array_map(fn ($title) => html_entity_decode($title), $matches[1]);
}

beforeEach(function () {
    Cache::flush();
});

test('the ranking page loads for guests and is linked from the menu', function () {
    $this->get('/')->assertOk()->assertSee(route('ranking'), false)->assertSee('Bảng xếp hạng');

    $this->get(route('ranking'))->assertOk()->assertSee('Bảng xếp hạng')->assertSee('Chưa có dữ liệu');
});

test('the weekly most-read ranking orders by reads in the last seven days', function () {
    $top = Story::factory()->create(['status_publish' => true, 'title' => 'Đọc nhiều nhất']);
    $second = Story::factory()->create(['status_publish' => true, 'title' => 'Đọc nhiều nhì']);
    $old = Story::factory()->create(['status_publish' => true, 'title' => 'Chỉ đọc nhiều từ lâu']);
    rankStat($top, 1, 50);
    rankStat($second, 100, 10);
    rankStat($old, 1, 900, today()->subDays(10)->toDateString());

    $response = $this->get(route('ranking', ['loai' => 'doc', 'thoi-gian' => 'tuan']))->assertOk();

    expect(rankedTitles($response))->toBe(['Đọc nhiều nhất', 'Đọc nhiều nhì']);
});

test('the monthly ranking includes older days that the weekly one leaves out', function () {
    $older = Story::factory()->create(['status_publish' => true, 'title' => 'Hot cách đây 20 ngày']);
    rankStat($older, 5, 40, today()->subDays(20)->toDateString());

    expect(rankedTitles($this->get(route('ranking', ['loai' => 'doc', 'thoi-gian' => 'tuan']))))->toBe([])
        ->and(rankedTitles($this->get(route('ranking', ['loai' => 'doc', 'thoi-gian' => 'thang']))))->toBe(['Hot cách đây 20 ngày']);
});

test('the most-viewed ranking uses views, not reads', function () {
    $viewed = Story::factory()->create(['status_publish' => true, 'title' => 'Nhiều lượt xem']);
    $read = Story::factory()->create(['status_publish' => true, 'title' => 'Nhiều lượt đọc']);
    rankStat($viewed, 90, 1);
    rankStat($read, 10, 80);

    expect(rankedTitles($this->get(route('ranking', ['loai' => 'xem', 'thoi-gian' => 'tuan']))))->toBe(['Nhiều lượt xem', 'Nhiều lượt đọc'])
        ->and(rankedTitles($this->get(route('ranking', ['loai' => 'doc', 'thoi-gian' => 'tuan']))))->toBe(['Nhiều lượt đọc', 'Nhiều lượt xem']);
});

test('the all-time rankings use the lifetime counters', function () {
    Story::factory()->create(['status_publish' => true, 'title' => 'Kỷ lục đọc', 'reads_count' => 900, 'views' => 10]);
    Story::factory()->create(['status_publish' => true, 'title' => 'Ít đọc', 'reads_count' => 5, 'views' => 5000]);
    Story::factory()->create(['status_publish' => true, 'title' => 'Không ai đọc', 'reads_count' => 0, 'views' => 0]);

    expect(rankedTitles($this->get(route('ranking', ['loai' => 'doc', 'thoi-gian' => 'tat-ca']))))->toBe(['Kỷ lục đọc', 'Ít đọc'])
        ->and(rankedTitles($this->get(route('ranking', ['loai' => 'xem', 'thoi-gian' => 'tat-ca']))))->toBe(['Ít đọc', 'Kỷ lục đọc']);
});

test('the favorites ranking counts favorites added in the period', function () {
    $recent = Story::factory()->create(['status_publish' => true, 'title' => 'Được yêu thích gần đây', 'favorite_count' => 2]);
    $older = Story::factory()->create(['status_publish' => true, 'title' => 'Yêu thích từ lâu', 'favorite_count' => 9]);
    foreach (User::factory()->count(2)->create() as $user) {
        Favorite::create(['user_id' => $user->id, 'story_id' => $recent->id]);
    }
    $oldFavorite = Favorite::create(['user_id' => User::factory()->create()->id, 'story_id' => $older->id]);
    $oldFavorite->forceFill(['created_at' => now()->subDays(40)])->save();

    expect(rankedTitles($this->get(route('ranking', ['loai' => 'yeu-thich', 'thoi-gian' => 'tuan']))))->toBe(['Được yêu thích gần đây'])
        ->and(rankedTitles($this->get(route('ranking', ['loai' => 'yeu-thich', 'thoi-gian' => 'tat-ca']))))->toBe(['Yêu thích từ lâu', 'Được yêu thích gần đây']);
});

test('the rating ranking needs at least five ratings and ignores the period', function () {
    Story::factory()->create(['status_publish' => true, 'title' => 'Điểm cao nhiều đánh giá', 'average_rating' => 4.8, 'rating_count' => 30]);
    Story::factory()->create(['status_publish' => true, 'title' => 'Điểm cao ít đánh giá', 'average_rating' => 5.0, 'rating_count' => 2]);
    Story::factory()->create(['status_publish' => true, 'title' => 'Điểm trung bình', 'average_rating' => 3.9, 'rating_count' => 10]);

    $response = $this->get(route('ranking', ['loai' => 'danh-gia', 'thoi-gian' => 'tuan']))
        ->assertOk()
        ->assertSee('từ 5 lượt đánh giá')
        ->assertDontSee('7 ngày');

    expect(rankedTitles($response))->toBe(['Điểm cao nhiều đánh giá', 'Điểm trung bình']);
});

test('unpublished and soft-deleted stories never appear', function () {
    $draft = Story::factory()->create(['status_publish' => false, 'title' => 'Bản nháp hot', 'reads_count' => 500]);
    $deleted = Story::factory()->create(['status_publish' => true, 'title' => 'Đã xóa hot', 'reads_count' => 500]);
    $visible = Story::factory()->create(['status_publish' => true, 'title' => 'Đang hiển thị', 'reads_count' => 1]);
    foreach ([$draft, $deleted, $visible] as $story) {
        rankStat($story, 10, 10);
    }
    $deleted->delete();

    foreach (['tuan', 'tat-ca'] as $period) {
        Cache::flush();
        expect(rankedTitles($this->get(route('ranking', ['loai' => 'doc', 'thoi-gian' => $period]))))->toBe(['Đang hiển thị']);
    }
});

test('a story unpublished after the ranking was cached disappears immediately', function () {
    $story = Story::factory()->create(['status_publish' => true, 'title' => 'Sắp bị gỡ', 'reads_count' => 50]);
    rankStat($story, 5, 50);

    expect(rankedTitles($this->get(route('ranking'))))->toBe(['Sắp bị gỡ']);

    $story->forceFill(['status_publish' => false])->save();

    expect(rankedTitles($this->get(route('ranking'))))->toBe([]);
});

test('the ranking is limited to fifty stories and cached', function () {
    foreach (range(1, 55) as $i) {
        rankStat(Story::factory()->create(['status_publish' => true]), 1, $i);
    }

    expect(rankedTitles($this->get(route('ranking'))))->toHaveCount(50)
        ->and(Cache::has('ranking:doc:tuan'))->toBeTrue();
});

test('unknown types and periods fall back to the defaults instead of failing', function () {
    $this->get(route('ranking', ['loai' => 'x', 'thoi-gian' => 'y']))->assertOk();
    $this->get('/bang-xep-hang?loai[]=a')->assertOk();
});

test('the author name in the ranking links to the author search', function () {
    $story = Story::factory()->create(['status_publish' => true, 'author_name' => 'Tô Hoài', 'reads_count' => 5]);
    rankStat($story, 5, 5);

    $this->get(route('ranking'))->assertOk()->assertSee(route('search', ['author' => 'Tô Hoài']), false);
});

test('the ranking follows new activity once the cache expires', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $first = Story::factory()->create(['status_publish' => true, 'title' => 'Dẫn đầu cũ']);
    $second = Story::factory()->create(['status_publish' => true, 'title' => 'Vượt lên']);
    rankStat($first, 1, 10);
    rankStat($second, 1, 5);

    expect(rankedTitles($this->get(route('ranking'))))->toBe(['Dẫn đầu cũ', 'Vượt lên']);

    DB::table('story_daily_stats')->where('story_id', $second->id)->update(['reads' => 50]);
    $this->travel(11)->minutes();

    expect(rankedTitles($this->get(route('ranking'))))->toBe(['Vượt lên', 'Dẫn đầu cũ']);
});
