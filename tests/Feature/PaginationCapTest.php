<?php

use App\Models\Story;

test('a huge page number is clamped instead of running a huge offset', function () {
    Story::factory()->create(['status_publish' => true]);

    $response = $this->get(route('stories.index', ['page' => 999999]));

    $response->assertOk();
    $response->assertViewHas('stories', fn ($paginator) => $paginator->currentPage() === 1000);
});

test('a non-numeric page parameter falls back to the first page', function () {
    Story::factory()->create(['status_publish' => true]);

    $response = $this->get('/danh-sach-truyen?page[]=1');

    $response->assertOk();
    $response->assertViewHas('stories', fn ($paginator) => $paginator->currentPage() === 1);
});
