<?php

use Illuminate\Support\Facades\Cache;

test('homepage does not cache arbitrary recent pagination values', function () {
    $this->get('/?recent=999999')
        ->assertOk();

    expect(Cache::has('home:recently_updated:page:999999'))->toBeFalse();
});

test('homepage normalizes invalid recent pagination values before caching', function () {
    $this->get('/?recent[]=999999')
        ->assertOk();

    expect(Cache::has('home:recently_updated:page:1'))->toBeTrue()
        ->and(Cache::has('home:recently_updated:page:Array'))->toBeFalse();
});

test('robots txt publishes the current application sitemap URL', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));
});
