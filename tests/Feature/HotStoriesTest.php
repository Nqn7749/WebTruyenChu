<?php

declare(strict_types=1);

use App\Models\Chapter;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\HotStoriesService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function dailyStat(Story $story, int $views, int $reads, ?string $date = null): void
{
    DB::table('story_daily_stats')->insert([
        'story_id' => $story->id,
        'stat_date' => $date ?? today()->toDateString(),
        'views' => $views,
        'reads' => $reads,
    ]);
}

function hotAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create(['role_id' => $role->id, 'status' => true, 'email_verified_at' => now()]);
}

beforeEach(function () {
    config(['stories.hot.window_days' => 7, 'stories.hot.limit' => 3, 'stories.hot.min_score' => 10]);
});

test('a counted view and a real read are added to todays statistics', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    Cache::flush();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (test)'])->get(route('chapters.show', [$story, $chapter->slug]))->assertOk();
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (test)'])->postJson(route('chapters.record-read', [$story, $chapter->slug]))->assertOk();

    $row = DB::table('story_daily_stats')->where('story_id', $story->id)->first();

    expect($row->stat_date)->toBe(today()->toDateString())
        ->and((int) $row->views)->toBe(1)
        ->and((int) $row->reads)->toBe(1);

    // A second real read from another visitor adds to the same row instead of creating one.
    HotStoriesService::recordRead($story->id);
    expect((int) DB::table('story_daily_stats')->where('story_id', $story->id)->value('reads'))->toBe(2)
        ->and(DB::table('story_daily_stats')->where('story_id', $story->id)->count())->toBe(1);
});

test('bots do not add to the statistics', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    Cache::flush();

    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get(route('chapters.show', [$story, $chapter->slug]))->assertOk();

    expect(DB::table('story_daily_stats')->count())->toBe(0);
});

test('the ranking marks the most active stories of the window as hot', function () {
    $top = Story::factory()->create(['status_publish' => true]);
    $second = Story::factory()->create(['status_publish' => true]);
    $quiet = Story::factory()->create(['status_publish' => true]);
    $old = Story::factory()->create(['status_publish' => true]);
    dailyStat($top, 5, 20);     // 65
    dailyStat($second, 30, 2);  // 36
    dailyStat($quiet, 3, 1);    // 6, under the minimum of 10
    dailyStat($old, 500, 500, today()->subDays(8)->toDateString()); // outside the 7-day window

    $result = app(HotStoriesService::class)->refresh();

    expect($result)->toBe(['marked' => 2, 'unmarked' => 0])
        ->and($top->fresh()->is_hot)->toBeTrue()
        ->and($second->fresh()->is_hot)->toBeTrue()
        ->and($quiet->fresh()->is_hot)->toBeFalse()
        ->and($old->fresh()->is_hot)->toBeFalse();
});

test('only the configured number of stories is marked and ties prefer the newer story', function () {
    $stories = collect(range(1, 5))->map(function () {
        $story = Story::factory()->create(['status_publish' => true]);
        dailyStat($story, 20, 0);

        return $story;
    });

    app(HotStoriesService::class)->refresh();

    expect(Story::query()->where('is_hot', true)->pluck('id')->sort()->values()->all())
        ->toBe($stories->slice(2)->pluck('id')->sort()->values()->all());
});

test('stories that fall out of the ranking lose the automatic hot flag', function () {
    $fading = Story::factory()->create(['status_publish' => true, 'is_hot' => true]);
    $rising = Story::factory()->create(['status_publish' => true]);
    dailyStat($fading, 1, 0);
    dailyStat($rising, 30, 5);

    $result = app(HotStoriesService::class)->refresh();

    expect($result)->toBe(['marked' => 1, 'unmarked' => 1])
        ->and($fading->fresh()->is_hot)->toBeFalse()
        ->and($rising->fresh()->is_hot)->toBeTrue();
});

test('unpublished and soft-deleted stories are never marked', function () {
    $draft = Story::factory()->create(['status_publish' => false]);
    $deleted = Story::factory()->create(['status_publish' => true]);
    dailyStat($draft, 99, 99);
    dailyStat($deleted, 99, 99);
    $deleted->delete();

    app(HotStoriesService::class)->refresh();

    expect(Story::withTrashed()->where('is_hot', true)->count())->toBe(0);
});

test('stories pinned by an admin are left alone by the ranking', function () {
    $pinnedOn = Story::factory()->create(['status_publish' => true, 'is_hot' => true, 'hot_override' => 'on']);
    $pinnedOff = Story::factory()->create(['status_publish' => true, 'is_hot' => false, 'hot_override' => 'off']);
    $auto = Story::factory()->create(['status_publish' => true]);
    dailyStat($pinnedOff, 100, 100);
    dailyStat($auto, 40, 5);

    app(HotStoriesService::class)->refresh();

    expect($pinnedOn->fresh()->is_hot)->toBeTrue()
        ->and($pinnedOff->fresh()->is_hot)->toBeFalse()
        ->and($auto->fresh()->is_hot)->toBeTrue();
});

test('refreshing keeps updated_at and clears the home cache only when something changed', function () {
    $story = Story::factory()->create(['status_publish' => true, 'updated_at' => now()->subDays(3)]);
    dailyStat($story, 50, 5);
    Cache::put('home:hot', 'stale', 600);
    $before = $story->fresh()->updated_at;

    app(HotStoriesService::class)->refresh();

    expect($story->fresh()->updated_at->equalTo($before))->toBeTrue()
        ->and(Cache::has('home:hot'))->toBeFalse();

    Cache::put('home:hot', 'fresh', 600);
    app(HotStoriesService::class)->refresh();

    expect(Cache::get('home:hot'))->toBe('fresh');
});

test('statistics older than the window plus a grace period are pruned', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    dailyStat($story, 1, 1, today()->subDays(40)->toDateString());
    dailyStat($story, 1, 1, today()->subDays(3)->toDateString());

    app(HotStoriesService::class)->refresh();

    expect(DB::table('story_daily_stats')->count())->toBe(1);
});

test('the artisan command runs the ranking and is scheduled hourly', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    dailyStat($story, 50, 5);

    $this->artisan('stories:refresh-hot')->expectsOutputToContain('1 marked')->assertSuccessful();

    expect($story->fresh()->is_hot)->toBeTrue();

    $scheduled = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'stories:refresh-hot'));

    expect($scheduled)->not->toBeNull()->and($scheduled->expression)->toBe('0 * * * *');
});

test('the home page hot section shows automatically ranked stories', function () {
    Cache::flush();
    $story = Story::factory()->create(['status_publish' => true, 'title' => 'Truyện đang lên']);
    dailyStat($story, 50, 5);

    app(HotStoriesService::class)->refresh();

    $this->get('/')->assertOk()->assertSee('Truyện đang lên');
});

test('toggling hot pins the decision and handing it back restores the automatic ranking', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $admin = hotAdmin();
    $story = Story::factory()->create(['status_publish' => true]);
    dailyStat($story, 50, 5);
    app(HotStoriesService::class)->refresh();
    expect($story->fresh()->is_hot)->toBeTrue();

    // The admin turns Hot off: it stays off even though the ranking would mark it.
    $this->actingAs($admin)->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'is_hot'])->assertSessionHasNoErrors();
    expect($story->fresh()->is_hot)->toBeFalse()->and($story->fresh()->hot_override)->toBe('off');

    app(HotStoriesService::class)->refresh();
    expect($story->fresh()->is_hot)->toBeFalse();

    // The admin turns it on: pinned on, independent of activity.
    $this->actingAs($admin)->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'is_hot']);
    expect($story->fresh()->is_hot)->toBeTrue()->and($story->fresh()->hot_override)->toBe('on');

    // Back to automatic: the ranking decides again (this story is active, so it is hot).
    $this->actingAs($admin)->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'hot_auto'])->assertSessionHas('success');
    expect($story->fresh()->hot_override)->toBeNull()->and($story->fresh()->is_hot)->toBeTrue();

    $quiet = Story::factory()->create(['status_publish' => true, 'is_hot' => true, 'hot_override' => 'on']);
    $this->actingAs($admin)->patch(route('admin.stories.toggle-flag', $quiet), ['flag' => 'hot_auto']);
    expect($quiet->fresh()->hot_override)->toBeNull()->and($quiet->fresh()->is_hot)->toBeFalse();
});

test('the other admin flags do not touch the hot override', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $story = Story::factory()->create(['status_publish' => true]);

    $this->actingAs(hotAdmin())->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'is_featured'])->assertSessionHasNoErrors();

    expect($story->fresh()->is_featured)->toBeTrue()->and($story->fresh()->hot_override)->toBeNull();
});

test('the admin list marks automatic hot stories and offers handing back pinned ones', function () {
    $auto = Story::factory()->create(['status_publish' => true, 'is_hot' => true, 'title' => 'Hot tự động']);
    $pinned = Story::factory()->create(['status_publish' => true, 'is_hot' => true, 'hot_override' => 'on', 'title' => 'Hot ghim tay']);

    $this->actingAs(hotAdmin())->get(route('admin.stories.index'))
        ->assertOk()
        ->assertSee('Hot (tự động)')
        ->assertSee('Hot: tự động');
});
