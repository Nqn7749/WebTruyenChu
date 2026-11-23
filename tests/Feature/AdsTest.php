<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function adsAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create(['role_id' => $role->id, 'status' => true, 'email_verified_at' => now()]);
}

function makeAd(array $overrides = []): Ad
{
    return Ad::create(array_merge([
        'name' => 'Quảng cáo thử',
        'placement' => 'home_left',
        'type' => 'image',
        'image_path' => 'ads/test.png',
        'link_url' => 'https://advertiser.example/landing',
        'alt_text' => 'Mô tả ảnh',
        'is_active' => true,
        'sort_order' => 0,
    ], $overrides));
}

function adPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Banner mới',
        'placement' => 'home_right',
        'type' => 'image',
        'link_url' => 'https://advertiser.example/offer',
        'alt_text' => 'Khuyến mãi',
        'is_active' => '1',
        'sort_order' => 1,
        'image' => UploadedFile::fake()->image('banner.png', 160, 600),
    ], $overrides);
}

beforeEach(function () {
    Cache::flush();
    Storage::fake('public');
});

// ---------------------------------------------------------------- placements and scheduling

test('there are left and right placements for the home, story, chapter and list pages', function () {
    expect(array_keys(Ad::PLACEMENTS))->toBe([
        'home_left', 'home_right', 'story_left', 'story_right', 'chapter_left', 'chapter_right', 'list_left', 'list_right',
    ]);
});

test('an ad runs only while it is on and inside its schedule', function () {
    $always = makeAd(['name' => 'Luôn chạy']);
    makeAd(['name' => 'Đã tắt', 'is_active' => false]);
    makeAd(['name' => 'Chưa tới giờ', 'starts_at' => now()->addDay()]);
    makeAd(['name' => 'Hết hạn', 'ends_at' => now()->subDay()]);
    $windowed = makeAd(['name' => 'Đang trong lịch', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    expect(Ad::running()->pluck('id')->sort()->values()->all())->toBe([$always->id, $windowed->id])
        ->and(Ad::query()->where('name', 'Đã tắt')->first()->state())->toBe('off')
        ->and(Ad::query()->where('name', 'Chưa tới giờ')->first()->state())->toBe('scheduled')
        ->and(Ad::query()->where('name', 'Hết hạn')->first()->state())->toBe('expired')
        ->and($always->state())->toBe('running');
});

test('forPlacement returns the running ads in order, at most three, and ignores unknown placements', function () {
    foreach (range(1, 5) as $i) {
        makeAd(['name' => 'Số '.$i, 'sort_order' => 10 - $i]);
    }
    makeAd(['name' => 'Chỗ khác', 'placement' => 'home_right']);

    $ads = Ad::forPlacement('home_left');

    expect($ads)->toHaveCount(3)
        ->and($ads->pluck('name')->all())->toBe(['Số 5', 'Số 4', 'Số 3'])
        ->and(Ad::forPlacement('nowhere'))->toBeEmpty();
});

test('changing an ad refreshes the cached placement immediately', function () {
    $ad = makeAd(['name' => 'Bản đầu']);
    expect(Ad::forPlacement('home_left')->pluck('name')->all())->toBe(['Bản đầu']);

    $ad->update(['name' => 'Bản sửa']);
    expect(Ad::forPlacement('home_left')->pluck('name')->all())->toBe(['Bản sửa']);

    $ad->delete();
    expect(Ad::forPlacement('home_left'))->toBeEmpty();
});

// ---------------------------------------------------------------- public rails

test('the home page shows the left and right rail ads and the story page does not', function () {
    makeAd(['name' => 'Trái trang chủ', 'placement' => 'home_left', 'alt_text' => 'QC trái trang chủ']);
    makeAd(['name' => 'Phải trang chủ', 'placement' => 'home_right', 'alt_text' => 'QC phải trang chủ']);
    makeAd(['name' => 'Trái trang truyện', 'placement' => 'story_left', 'alt_text' => 'QC trái trang truyện']);

    $home = $this->get('/')->assertOk()
        ->assertSee('ad-rail-left', false)
        ->assertSee('ad-rail-right', false)
        ->assertSee('QC trái trang chủ')
        ->assertSee('QC phải trang chủ')
        ->assertDontSee('QC trái trang truyện');

    expect($home->getContent())->toContain('rel="sponsored nofollow noopener"');
});

test('each kind of page shows only the ads of its own placements', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    foreach (['home', 'story', 'chapter', 'list'] as $page) {
        makeAd(['name' => $page, 'placement' => $page.'_left', 'alt_text' => 'ALT-'.$page]);
    }

    $this->get('/')->assertSee('ALT-home')->assertDontSee('ALT-story')->assertDontSee('ALT-chapter')->assertDontSee('ALT-list');
    $this->get(route('stories.show', $story))->assertSee('ALT-story')->assertDontSee('ALT-home');
    $this->get(route('chapters.show', [$story, $chapter->slug]))->assertSee('ALT-chapter')->assertDontSee('ALT-story');
    $this->get(route('search'))->assertSee('ALT-list')->assertDontSee('ALT-home');
    $this->get(route('stories.index'))->assertSee('ALT-list');
    $this->get(route('ranking'))->assertSee('ALT-list');
    $this->get(route('categories.show', Category::factory()->create()))->assertSee('ALT-list');
});

test('pages without a placement and expired or disabled ads show no rail', function () {
    makeAd(['name' => 'Tắt', 'is_active' => false]);
    makeAd(['name' => 'Hết hạn', 'ends_at' => now()->subHour(), 'alt_text' => 'QC hết hạn']);

    $this->get('/')->assertOk()->assertDontSee('ad-rail', false)->assertDontSee('QC hết hạn');
    $this->get(route('login'))->assertOk()->assertDontSee('ad-rail', false);
});

test('an image ad links to the click counter, not straight to the advertiser', function () {
    $ad = makeAd();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('href="'.route('ads.click', $ad).'"')
        ->and($html)->not->toContain('advertiser.example');
});

test('a code ad is printed as pasted, on its own placement only', function () {
    makeAd(['type' => 'code', 'image_path' => null, 'link_url' => null, 'html_code' => '<div id="net-ad">Mã mạng quảng cáo</div>']);

    $this->get('/')->assertOk()->assertSee('<div id="net-ad">Mã mạng quảng cáo</div>', false);
    $this->get(route('search'))->assertOk()->assertDontSee('net-ad', false);
});

test('the rails stylesheet is loaded and hides rails below the wide breakpoint', function () {
    $this->get('/')->assertOk()->assertSee('css/ads.css', false);

    $css = file_get_contents(public_path('css/ads.css'));

    expect($css)->toContain('min-width: 1720px')->toContain('.ad-rail-left')->toContain('.ad-rail-right')
        ->toContain('reader-focus-mode');
});

// ---------------------------------------------------------------- click counter

test('a click is counted and redirected to the stored link', function () {
    $ad = makeAd();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (test)'])
        ->get(route('ads.click', $ad))
        ->assertRedirect('https://advertiser.example/landing')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($ad->fresh()->clicks)->toBe(1);
});

test('bots do not add to the click count but are still redirected', function () {
    $ad = makeAd();

    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get(route('ads.click', $ad))->assertRedirect('https://advertiser.example/landing');

    expect($ad->fresh()->clicks)->toBe(0);
});

test('disabled, expired, scheduled and code ads cannot be clicked through', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0']);

    foreach ([
        makeAd(['is_active' => false]),
        makeAd(['ends_at' => now()->subDay()]),
        makeAd(['starts_at' => now()->addDay()]),
        makeAd(['type' => 'code', 'link_url' => 'https://advertiser.example/x', 'html_code' => '<b>x</b>']),
    ] as $ad) {
        $this->get(route('ads.click', $ad))->assertNotFound();
        expect($ad->fresh()->clicks)->toBe(0);
    }
});

test('the click route never redirects anywhere the request asks for', function () {
    $ad = makeAd();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0'])
        ->get(route('ads.click', ['ad' => $ad->id, 'url' => 'https://evil.example', 'redirect' => 'https://evil.example']))
        ->assertRedirect('https://advertiser.example/landing');
});

test('robots.txt keeps crawlers away from the click route', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /qc/', false);
});

// ---------------------------------------------------------------- admin

test('only admins can manage ads', function () {
    $ad = makeAd();

    $this->get(route('admin.ads.index'))->assertRedirect();

    $user = User::factory()->create(['status' => true, 'email_verified_at' => now()]);
    $this->actingAs($user)->get(route('admin.ads.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.ads.store'), adPayload())->assertForbidden();
    $this->actingAs($user)->delete(route('admin.ads.destroy', $ad))->assertForbidden();

    expect(Ad::query()->count())->toBe(1);
});

test('the admin list shows placements, states and clicks and is linked from the menu', function () {
    makeAd(['name' => 'Đang chạy', 'clicks' => 12]);
    makeAd(['name' => 'Đã tắt', 'is_active' => false, 'placement' => 'story_right']);

    $this->actingAs(adsAdmin())->get(route('admin.ads.index'))
        ->assertOk()
        ->assertSee('Quản lý quảng cáo')
        ->assertSee('Trang chủ — rìa trái')
        ->assertSee('Trang đọc chương — rìa phải')
        ->assertSee('Đang chạy')
        ->assertSee('Đã tắt')
        ->assertSee('12')
        ->assertSee(route('admin.ads.index'), false);
});

test('an admin creates an image ad with a banner and a link', function () {
    $this->actingAs(adsAdmin())
        ->post(route('admin.ads.store'), adPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.ads.index'));

    $ad = Ad::query()->firstOrFail();

    expect($ad->placement)->toBe('home_right')
        ->and($ad->type)->toBe('image')
        ->and($ad->link_url)->toBe('https://advertiser.example/offer')
        ->and($ad->is_active)->toBeTrue()
        ->and($ad->image_path)->toStartWith('ads/');

    Storage::disk('public')->assertExists($ad->image_path);

    // It is live on the home page right away.
    $this->get('/')->assertSee('ad-rail-right', false)->assertSee('Khuyến mãi');
});

test('an admin creates a code ad and image fields are dropped', function () {
    $this->actingAs(adsAdmin())
        ->post(route('admin.ads.store'), [
            'name' => 'Mã', 'placement' => 'list_left', 'type' => 'code', 'html_code' => '<ins class="adsbygoogle"></ins>',
            'link_url' => 'https://ignored.example', 'is_active' => '1',
        ])
        ->assertSessionHasNoErrors();

    $ad = Ad::query()->firstOrFail();

    expect($ad->type)->toBe('code')->and($ad->html_code)->toBe('<ins class="adsbygoogle"></ins>')
        ->and($ad->link_url)->toBeNull()->and($ad->image_path)->toBeNull();
});

test('invalid ads are rejected and nothing is saved', function (array $override, string $field) {
    $this->actingAs(adsAdmin())
        ->post(route('admin.ads.store'), adPayload($override))
        ->assertSessionHasErrors($field);

    expect(Ad::query()->count())->toBe(0);
})->with([
    'no banner' => [['image' => null], 'image'],
    'svg banner' => [['image' => UploadedFile::fake()->createWithContent('b.svg', '<svg onload="alert(1)"></svg>')], 'image'],
    'banner over 1 MB' => [['image' => UploadedFile::fake()->create('big.png', 1500, 'image/png')], 'image'],
    'not an image' => [['image' => UploadedFile::fake()->create('x.pdf', 10)], 'image'],
    'no link' => [['link_url' => null], 'link_url'],
    'http link' => [['link_url' => 'http://advertiser.example'], 'link_url'],
    'javascript link' => [['link_url' => 'javascript:alert(1)'], 'link_url'],
    'unknown placement' => [['placement' => 'footer'], 'placement'],
    'unknown type' => [['type' => 'video'], 'type'],
    'no name' => [['name' => ''], 'name'],
    'tags in name' => [['name' => '<b>x</b>'], 'name'],
    'end before start' => [['starts_at' => '2030-01-02 00:00', 'ends_at' => '2030-01-01 00:00'], 'ends_at'],
    'negative order' => [['sort_order' => -1], 'sort_order'],
]);

test('a code ad needs its code', function () {
    $this->actingAs(adsAdmin())
        ->post(route('admin.ads.store'), ['name' => 'Mã', 'placement' => 'list_left', 'type' => 'code'])
        ->assertSessionHasErrors('html_code');
});

test('editing keeps the banner unless a new one is uploaded, and replacing deletes the old file', function () {
    $admin = adsAdmin();
    $this->actingAs($admin)->post(route('admin.ads.store'), adPayload())->assertSessionHasNoErrors();
    $ad = Ad::query()->firstOrFail();
    $original = $ad->image_path;

    $this->actingAs($admin)->put(route('admin.ads.update', $ad), [
        'name' => 'Đổi tên', 'placement' => 'story_left', 'type' => 'image', 'link_url' => 'https://advertiser.example/new', 'is_active' => '1',
    ])->assertSessionHasNoErrors();

    expect($ad->fresh()->image_path)->toBe($original)->and($ad->fresh()->name)->toBe('Đổi tên')->and($ad->fresh()->placement)->toBe('story_left');

    $this->actingAs($admin)->put(route('admin.ads.update', $ad), [
        'name' => 'Đổi tên', 'placement' => 'story_left', 'type' => 'image', 'link_url' => 'https://advertiser.example/new', 'is_active' => '1',
        'image' => UploadedFile::fake()->image('new.png', 160, 600),
    ])->assertSessionHasNoErrors();

    expect($ad->fresh()->image_path)->not->toBe($original);
    Storage::disk('public')->assertMissing($original);
    Storage::disk('public')->assertExists($ad->fresh()->image_path);
});

test('turning an image ad into a code ad removes its banner file', function () {
    $admin = adsAdmin();
    $this->actingAs($admin)->post(route('admin.ads.store'), adPayload())->assertSessionHasNoErrors();
    $ad = Ad::query()->firstOrFail();
    $file = $ad->image_path;

    $this->actingAs($admin)->put(route('admin.ads.update', $ad), [
        'name' => 'Giờ là mã', 'placement' => 'home_right', 'type' => 'code', 'html_code' => '<i>mã</i>', 'is_active' => '1',
    ])->assertSessionHasNoErrors();

    expect($ad->fresh()->image_path)->toBeNull()->and($ad->fresh()->type)->toBe('code');
    Storage::disk('public')->assertMissing($file);
});

test('an admin can switch an ad off and on and the site follows at once', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $admin = adsAdmin();
    $ad = makeAd(['alt_text' => 'QC bật tắt']);

    $this->get('/')->assertSee('QC bật tắt');

    $this->actingAs($admin)->patch(route('admin.ads.toggle', $ad))->assertSessionHas('success');
    expect($ad->fresh()->is_active)->toBeFalse();
    $this->get('/')->assertDontSee('QC bật tắt');

    $this->actingAs($admin)->patch(route('admin.ads.toggle', $ad));
    expect($ad->fresh()->is_active)->toBeTrue();
    $this->get('/')->assertSee('QC bật tắt');
});

test('deleting an ad removes its banner and takes it off the site', function () {
    $admin = adsAdmin();
    $this->actingAs($admin)->post(route('admin.ads.store'), adPayload(['placement' => 'home_left', 'alt_text' => 'QC sẽ xóa']))->assertSessionHasNoErrors();
    $ad = Ad::query()->firstOrFail();
    $file = $ad->image_path;
    $this->get('/')->assertSee('QC sẽ xóa');

    $this->actingAs($admin)->delete(route('admin.ads.destroy', $ad))->assertRedirect(route('admin.ads.index'));

    expect(Ad::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($file);
    $this->get('/')->assertDontSee('QC sẽ xóa');
});

test('ad changes are written to the admin activity log', function () {
    $admin = adsAdmin();
    $this->actingAs($admin)->post(route('admin.ads.store'), adPayload(['name' => 'Có nhật ký']))->assertSessionHasNoErrors();
    $ad = Ad::query()->firstOrFail();
    $this->actingAs($admin)->patch(route('admin.ads.toggle', $ad));
    $this->actingAs($admin)->delete(route('admin.ads.destroy', $ad));

    $actions = AdminActivityLog::query()->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe(['ad.created', 'ad.disabled', 'ad.deleted']);
});

test('the edit form shows the current values', function () {
    $ad = makeAd(['name' => 'Tên hiện tại', 'link_url' => 'https://advertiser.example/current']);

    $this->actingAs(adsAdmin())->get(route('admin.ads.edit', $ad))
        ->assertOk()
        ->assertSee('Tên hiện tại')
        ->assertSee('https://advertiser.example/current');

    $this->actingAs(adsAdmin())->get(route('admin.ads.create'))->assertOk()->assertSee('Thêm quảng cáo');
});

test('banner images use a site-relative url so they load whatever host or port the site runs on', function () {
    config(['app.url' => 'http://localhost']);
    $ad = makeAd(['image_path' => 'ads/test.png']);

    expect($ad->imageUrl())->toBe('/storage/ads/test.png');

    $this->get('/')->assertOk()->assertSee('src="/storage/ads/test.png"', false)->assertDontSee('http://localhost/storage', false);
    $this->actingAs(adsAdmin())->get(route('admin.ads.index'))->assertOk()->assertSee('src="/storage/ads/test.png"', false);
});

test('every rail has a close button and a script that remembers the choice for the viewing session only', function () {
    makeAd(['placement' => 'home_left']);
    makeAd(['name' => 'Phải', 'placement' => 'home_right']);

    $html = $this->get('/')->assertOk()->getContent();

    // The layout has its own theme script that uses localStorage, so look at the ads script only.
    preg_match('#<script>\s*\(\(\) => \{\s*// A reader who closes the ads.*?</script>#s', $html, $match);
    $script = $match[0] ?? '';

    expect(substr_count($html, 'data-ad-close'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('aria-label="Tắt quảng cáo"')
        ->and($script)->toContain('sessionStorage.getItem(KEY)')
        ->toContain("sessionStorage.setItem(KEY, '1')")
        ->not->toContain('localStorage')
        ->not->toContain('Date.now()');
});

test('pages without any ad carry neither the rails nor the close script', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('ad_rails_closed')->not->toContain('data-ad-close');
});

test('the left rail runs over the full screen height and the right rail keeps the 600px box', function () {
    $css = file_get_contents(public_path('css/ads.css'));

    expect($css)
        ->toContain('--ad-width: 160px')
        ->toContain('width: var(--ad-width)')
        // left: from under the header to 16px above the bottom edge, images fill their box
        ->toContain('top: 96px')
        ->toContain('bottom: 16px')
        ->toContain('object-fit: cover')
        ->not->toContain('object-fit: contain')
        // right: not stretched, a box of at most 600px, bottom of a taller image cropped
        ->toContain('.ad-rail-right {')
        ->toContain('--ad-max-height: 600px')
        ->toContain('bottom: auto')
        ->toContain('.ad-rail-right .ad-item img')
        ->toContain('object-position: center top')
        ->toContain('.ad-rail[hidden]');
});

test('the ads of a rail share its height equally and nothing scrolls', function () {
    $css = file_get_contents(public_path('css/ads.css'));

    expect($css)
        ->toContain('flex: 1 1 0')
        ->toContain('min-height: 0')
        ->toContain('--ad-share: calc((var(--ad-total-height) - var(--ad-gap) * (var(--ad-count, 1) - 1)) / var(--ad-count, 1))')
        ->not->toContain('overflow-y: auto')
        ->not->toContain('scrollbar');
});

test('each rail tells the stylesheet how many ads share its height', function () {
    makeAd(['name' => 'Một', 'placement' => 'home_left']);
    makeAd(['name' => 'Hai', 'placement' => 'home_left', 'sort_order' => 1]);
    makeAd(['name' => 'Phải', 'placement' => 'home_right']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('ad-rail-left" aria-label="Quảng cáo" style="--ad-count: 2"')
        ->toContain('ad-rail-right" aria-label="Quảng cáo" style="--ad-count: 1"');
});

test('there is no size or aspect ratio check on the banner, only the file size', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $admin = adsAdmin();

    foreach ([[20, 20], [160, 600], [1200, 300], [400, 3000]] as $i => [$w, $h]) {
        $this->actingAs($admin)
            ->post(route('admin.ads.store'), adPayload(['name' => 'Cỡ '.$w.'x'.$h, 'image' => UploadedFile::fake()->image('b'.$i.'.png', $w, $h)]))
            ->assertSessionHasNoErrors();
    }

    expect(Ad::query()->count())->toBe(4);
});

test('story and chapter pages render safe in-page banners for mobile and laptop', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    $storyAd = makeAd(['name' => 'Banner truyện', 'placement' => 'story_right', 'alt_text' => 'QC truyện']);
    $chapterAd = makeAd(['name' => 'Banner chương', 'placement' => 'chapter_right', 'alt_text' => 'QC chương']);

    $storyResponse = $this->get(route('stories.show', $story))->assertOk();
    $storyResponse->assertSee('ad-inpage-banner', false)
        ->assertSee('QC truyện')
        ->assertSee(route('ads.click', $storyAd), false);

    $chapterResponse = $this->get(route('chapters.show', [$story, $chapter->slug]))->assertOk();
    $chapterResponse->assertSee('ad-inpage-banner', false)
        ->assertSee('QC chương')
        ->assertSee(route('ads.click', $chapterAd), false);
});
