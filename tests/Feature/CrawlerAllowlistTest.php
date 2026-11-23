<?php

declare(strict_types=1);

use App\Jobs\RunCrawlerSource;
use App\Models\CrawlerRun;
use App\Models\CrawlerSource;
use App\Models\Role;
use App\Models\User;
use App\Services\StoryUrlImportService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

function allowlistCrawlerAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create(['role_id' => $role->id, 'status' => true, 'email_verified_at' => now()]);
}

function allowlistCrawlerPayload(string $host): array
{
    return [
        'name' => 'Nguồn thử',
        'base_url' => 'https://'.$host,
        'listing_url' => 'https://'.$host.'/stories',
        'permission_confirmed' => '1',
        'enabled' => '1',
        'selectors' => [
            'story_link' => '//a[@class="story"]',
            'story_title' => '//h1',
            'story_author' => '',
            'story_description' => '',
            'chapter_link' => '//a[@class="chapter"]',
            'chapter_title' => '//h1',
            'chapter_content' => '//div[@class="content"]',
        ],
    ];
}

function queuedRunFor(string $host): CrawlerRun
{
    $source = CrawlerSource::create(allowlistCrawlerPayload($host));

    return CrawlerRun::create(['crawler_source_id' => $source->id, 'status' => 'queued']);
}

test('without the allowlist any public host may be registered, as before', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['story_import.require_allowlist' => false, 'story_import.sources' => []]);

    $this->actingAs(allowlistCrawlerAdmin())
        ->post(route('admin.crawler.store'), allowlistCrawlerPayload('licensed.example'))
        ->assertSessionHasNoErrors();

    expect(CrawlerSource::query()->count())->toBe(1);
});

test('with the allowlist on, a crawler source host must be listed in story_import sources', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['story_import.require_allowlist' => true, 'story_import.sources' => ['allowed.example' => []]]);
    $admin = allowlistCrawlerAdmin();

    $this->actingAs($admin)
        ->post(route('admin.crawler.store'), allowlistCrawlerPayload('other.example'))
        ->assertSessionHasErrors('base_url');

    expect(CrawlerSource::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->post(route('admin.crawler.store'), allowlistCrawlerPayload('Allowed.Example'))
        ->assertSessionHasNoErrors();

    expect(CrawlerSource::query()->count())->toBe(1);
});

test('a run for a source whose host is not allowed is cancelled before any request', function () {
    config(['story_import.require_allowlist' => true, 'story_import.sources' => ['allowed.example' => []]]);
    Http::fake();

    $run = queuedRunFor('8.8.8.8');

    (new RunCrawlerSource($run->id))->handle();

    expect($run->fresh()->status)->toBe('cancelled')
        ->and($run->fresh()->message)->toContain('danh sách cho phép');

    Http::assertNothingSent();
});

test('a listed host still crawls with the allowlist on', function () {
    config(['story_import.require_allowlist' => true, 'story_import.sources' => ['8.8.8.8' => []]]);
    Http::fake(['https://8.8.8.8/stories' => Http::response('<html><body>nothing</body></html>', 200)]);

    $run = queuedRunFor('8.8.8.8');

    (new RunCrawlerSource($run->id))->handle();

    // It reached the page and failed on the missing story links, not on the allowlist.
    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->message)->toContain('Không tìm thấy liên kết truyện');
});

test('the crawler now uses the hardened client: internal and ipv6 targets are refused before a request', function (string $host) {
    Http::fake();

    $run = queuedRunFor($host);

    try {
        (new RunCrawlerSource($run->id))->handle();
    } catch (Throwable) {
        // The job rethrows after marking the run failed.
    }

    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->message)->toContain('không được phép');

    Http::assertNothingSent();
})->with([
    'private ipv4' => '10.0.0.5',
    'loopback' => '127.0.0.1',
    'ipv4-mapped ipv6' => '[::ffff:127.0.0.1]',
    'unique local ipv6' => '[fd00::1]',
]);

test('a hostname resolving to an internal address is refused for the crawler as well', function () {
    Http::fake();
    app()->bind(StoryUrlImportService::class, fn () => new class extends StoryUrlImportService
    {
        protected function resolveHost(string $host): array
        {
            return ['8.8.8.8', '10.0.0.9'];
        }
    });

    $run = queuedRunFor('mixed.example');

    try {
        (new RunCrawlerSource($run->id))->handle();
    } catch (Throwable) {
    }

    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->message)->toContain('không được phép');

    Http::assertNothingSent();
});

test('the crawler refuses oversized pages', function () {
    Http::fake([
        'https://8.8.8.8/stories' => Http::response(str_repeat('a', 2_000_001), 200, ['Content-Type' => 'text/html']),
    ]);
    $run = queuedRunFor('8.8.8.8');

    try {
        (new RunCrawlerSource($run->id))->handle();
    } catch (Throwable) {
    }

    expect($run->fresh()->status)->toBe('failed')->and($run->fresh()->message)->toContain('2 MB');
});

test('the crawler refuses non-html responses', function () {
    Http::fake(['https://8.8.8.8/stories' => Http::response('{"a":1}', 200, ['Content-Type' => 'application/json'])]);
    $run = queuedRunFor('8.8.8.8');

    try {
        (new RunCrawlerSource($run->id))->handle();
    } catch (Throwable) {
    }

    expect($run->fresh()->status)->toBe('failed')->and($run->fresh()->message)->toContain('không trả về trang HTML');
});

test('hostAllowed treats the allowlist case-insensitively and ignores it when off', function () {
    config(['story_import.require_allowlist' => false, 'story_import.sources' => []]);
    expect(StoryUrlImportService::hostAllowed('anything.example'))->toBeTrue();

    config(['story_import.require_allowlist' => true, 'story_import.sources' => ['Source.Example' => []]]);
    expect(StoryUrlImportService::hostAllowed('SOURCE.example'))->toBeTrue()
        ->and(StoryUrlImportService::hostAllowed('source.example.'))->toBeTrue()
        ->and(StoryUrlImportService::hostAllowed('evil.example'))->toBeFalse();
});
