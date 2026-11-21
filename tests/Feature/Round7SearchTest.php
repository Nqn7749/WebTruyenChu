<?php

use App\Models\Story;

test('canonical drops the home recent param when it is 1 or invalid', function () {
    Story::factory()->create(['status_publish' => true]);

    $this->get('/?recent=abc')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost">', false);

    $this->get('/?recent=1')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost">', false);
});

test('canonical keeps the home recent param when it is a later page', function () {
    Story::factory()->create(['status_publish' => true]);

    $this->get('/?recent=5')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost?recent=5">', false);

    $this->get('/?recent=99999')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost?recent=100">', false);
});

test('cached search pagination links carry the current request query string', function () {
    foreach (range(1, 16) as $i) {
        Story::factory()->create([
            'title' => 'Truyện test '.$i,
            'description' => 'Mô tả truyện test.',
            'status_publish' => true,
        ]);
    }

    // Request đầu tiên ghi cache; link phân trang mang utm_source=first.
    $this->get('/tim-kiem?q='.urlencode('truyện').'&utm_source=first')
        ->assertOk()
        ->assertSee('utm_source=first', false);

    // Request thứ hai trúng cùng cache entry (utm_source không nằm trong
    // key cache) nhưng link phân trang phải mang utm_source của chính
    // nó, không phải của request đầu tiên.
    $this->get('/tim-kiem?q='.urlencode('truyện').'&utm_source=second')
        ->assertOk()
        ->assertSee('utm_source=second', false)
        ->assertDontSee('utm_source=first');
});
