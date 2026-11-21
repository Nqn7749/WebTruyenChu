<?php

declare(strict_types=1);

use App\Jobs\RunCrawlerSource;
use App\Models\CrawlerSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function crawlerAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create([
        'role_id' => $role->id,
        'status' => true,
        'email_verified_at' => now(),
    ]);
}

function validCrawlerPayload(): array
{
    return [
        'name' => 'Nguồn được cấp phép',
        'base_url' => 'https://licensed.example',
        'listing_url' => 'https://licensed.example/stories',
        'permission_confirmed' => '1',
        'enabled' => '1',
        'selectors' => [
            'story_link' => '//a[@class="story"]',
            'story_title' => '//h1',
            'story_author' => '//span[@class="author"]',
            'story_description' => '//div[@class="description"]',
            'chapter_link' => '//a[@class="chapter"]',
            'chapter_title' => '//h1',
            'chapter_content' => '//div[@class="content"]',
        ],
    ];
}

test('admin can register a permission-confirmed crawler source', function () {
    $this->actingAs(crawlerAdmin())
        ->post(route('admin.crawler.store'), validCrawlerPayload())
        ->assertRedirect(route('admin.crawler.index'));

    expect(CrawlerSource::query()->count())->toBe(1)
        ->and(CrawlerSource::first()->enabled)->toBeTrue();
});

test('crawler source rejects non-https or mismatched hosts', function () {
    $payload = validCrawlerPayload();
    $payload['listing_url'] = 'http://other.example/stories';

    $this->actingAs(crawlerAdmin())
        ->post(route('admin.crawler.store'), $payload)
        ->assertSessionHasErrors('base_url');

    expect(CrawlerSource::query()->count())->toBe(0);
});

test('crawler cannot be queued without explicit permission confirmation', function () {
    $payload = validCrawlerPayload();
    unset($payload['permission_confirmed']);

    $this->actingAs(crawlerAdmin())
        ->post(route('admin.crawler.store'), $payload)
        ->assertSessionHasErrors('permission_confirmed');

    expect(CrawlerSource::query()->count())->toBe(0);
});

test('admin can queue a crawler run for an enabled authorized source', function () {
    Queue::fake();
    $source = CrawlerSource::create(validCrawlerPayload());

    $this->actingAs(crawlerAdmin())
        ->post(route('admin.crawler.run', $source))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(RunCrawlerSource::class);
});

test('crawler marks a run failed when the story link XPath returns no results', function () {
    $payload = validCrawlerPayload();
    $payload['base_url'] = 'https://8.8.8.8';
    $payload['listing_url'] = 'https://8.8.8.8/stories';
    $source = CrawlerSource::create($payload);
    $run = CrawlerRun::create([
        'crawler_source_id' => $source->id,
        'status' => 'queued',
    ]);

    Http::fake([
        'https://8.8.8.8/stories' => Http::response('<html><body><main>No stories here</main></body></html>', 200),
    ]);

    (new RunCrawlerSource($run->id))->handle();

    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->stories_found)->toBe(0)
        ->and($run->fresh()->message)->toContain('Không tìm thấy liên kết truyện');
});

test('crawler marks a run failed when story links exist but no story title is found', function () {
    $payload = validCrawlerPayload();
    $payload['base_url'] = 'https://8.8.8.8';
    $payload['listing_url'] = 'https://8.8.8.8/stories';
    $source = CrawlerSource::create($payload);
    $run = CrawlerRun::create([
        'crawler_source_id' => $source->id,
        'status' => 'queued',
    ]);

    Http::fake([
        'https://8.8.8.8/stories' => Http::response('<a class="story" href="/story/one">One</a>', 200),
        'https://8.8.8.8/story/one' => Http::response('<html><body><p>Missing heading</p></body></html>', 200),
    ]);

    (new RunCrawlerSource($run->id))->handle();

    expect($run->fresh()->status)->toBe('failed')
        ->and($run->fresh()->stories_found)->toBe(1)
        ->and($run->fresh()->message)->toContain('không đọc được tên truyện');
});
