<?php

declare(strict_types=1);

use App\Models\Story;
use Illuminate\Support\Facades\Cache;

test('the recently updated list comes before the hot stories on the home page', function () {
    Cache::flush();
    Story::factory()->create(['status_publish' => true, 'is_hot' => true, 'title' => 'Truyện đang hot', 'last_chapter_at' => now()]);
    Story::factory()->create(['status_publish' => true, 'title' => 'Truyện vừa cập nhật', 'last_chapter_at' => now()]);

    $html = $this->get('/')->assertOk()->getContent();

    $recent = strpos($html, '<h2>Mới cập nhật</h2>');
    $hot = strpos($html, '<h2>Truyện hot</h2>');
    $recommended = strpos($html, '<h2>Truyện đề cử</h2>');
    $newest = strpos($html, '<h2>Mới đăng</h2>');

    expect($recent)->not->toBeFalse()
        ->and($recent)->toBeLessThan($hot)
        ->and($hot)->toBeLessThan($recommended)
        ->and($recommended)->toBeLessThan($newest);
});
