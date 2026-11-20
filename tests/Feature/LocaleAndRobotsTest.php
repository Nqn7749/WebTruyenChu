<?php

use Illuminate\Support\Facades\Lang;

test('application defaults to Vietnam timezone and Vietnamese pagination labels', function () {
    expect(config('app.timezone'))->toBe('Asia/Ho_Chi_Minh')
        ->and(config('app.locale'))->toBe('vi')
        ->and(Lang::get('pagination.next'))->toBe('Trang sau &raquo;');
});

test('robots.txt uses the configured canonical host and excludes private application areas', function () {
    $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
        ->assertSee('Disallow: /sang-tac')
        ->assertSee('Disallow: /notifications')
        ->assertDontSee('your-domain.com');
});
