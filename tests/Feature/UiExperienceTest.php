<?php

test('public layout exposes accessible navigation and a persistent theme control', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Bỏ qua điều hướng')
        ->assertSee('id="main-content"', false)
        ->assertSee('id="themeToggle"', false)
        ->assertSee('aria-label="Mở hoặc đóng menu"', false)
        ->assertSee('class="app-page"', false)
        ->assertSee('class="container py-4 app-main"', false)
        ->assertSee('class="app-footer py-4 mt-5"', false);
});

test('search page keeps its filters labelled and keyboard accessible', function () {
    $response = $this->get(route('search'));

    $response->assertOk()
        ->assertSee('id="search-filters"', false)
        ->assertSee('for="search-keyword"', false)
        ->assertSee('for="search-category"', false)
        ->assertSee('for="search-status"', false)
        ->assertSee('for="search-sort"', false);
});
