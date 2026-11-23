<?php

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

test('category changes invalidate the navigation category cache', function () {
    Cache::put('nav_categories', collect(['stale']), now()->addHour());

    Category::create([
        'name' => 'Fantasy',
        'slug' => 'fantasy',
    ]);

    expect(Cache::has('nav_categories'))->toBeFalse();
});

test('the category menu lists genres A-Z with Đ sorted among D and renders as a multi-column menu', function () {
    Cache::forget('nav_categories');
    foreach (['Võng Du', 'Đô Thị', 'Ngôn Tình', 'Cung Đấu', 'Dị Giới', 'Xuyên Không'] as $name) {
        Category::factory()->create(['name' => $name, 'slug' => Str::slug($name), 'parent_id' => null]);
    }

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('category-menu');

    $positions = array_map(fn (string $name) => mb_strpos($html, '>'.$name.'</a>'), ['Cung Đấu', 'Dị Giới', 'Đô Thị', 'Ngôn Tình', 'Võng Du', 'Xuyên Không']);

    expect($positions)->each->not->toBeFalse()
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());
});

test('the category menu also lists child genres under their parent, A-Z', function () {
    Cache::forget('nav_categories');
    $parent = Category::factory()->create(['name' => 'Tiên Hiệp', 'slug' => 'tien-hiep', 'parent_id' => null]);
    foreach (['Tiên Hiệp Trọng Sinh', 'Tiên Hiệp Hệ Thống'] as $name) {
        Category::factory()->create(['name' => $name, 'slug' => Str::slug($name), 'parent_id' => $parent->id]);
    }
    Category::factory()->create(['name' => 'Ngôn Tình', 'slug' => 'ngon-tinh', 'parent_id' => null]);

    $html = $this->get('/')->assertOk()->getContent();

    $positions = array_map(fn (string $name) => mb_strpos($html, '>'.$name.'</a>'), [
        'Ngôn Tình', 'Tiên Hiệp', 'Tiên Hiệp Hệ Thống', 'Tiên Hiệp Trọng Sinh',
    ]);

    expect($positions)->each->not->toBeFalse()
        ->and($positions)->toBe(collect($positions)->sort()->values()->all())
        ->and($html)->toContain('category-child');
});
