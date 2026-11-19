<?php

use App\\Models\\Category;
use App\\Models\\Story;

test('search finds published stories by title author category and tag', function () {
    $category = Category::factory()->create(['name' => 'Kiếm hiệp']);
    $story = Story::factory()->create([
        'title' => 'Hành trình phương xa',
        'author_name' => 'Nguyễn Minh',
        'status_publish' => true,
    ]);
    $story->categories()->attach($category);
    $story->tags()->create(['name' => 'Tu tiên', 'slug' => 'tu-tien']);

    $this->get(route('search', ['q' => 'Nguyễn Minh']))
        ->assertOk()
        ->assertSee('Hành trình phương xa');

    $this->get(route('search', ['q' => 'Kiếm hiệp']))
        ->assertOk()
        ->assertSee('Hành trình phương xa');

    $this->get(route('search', ['q' => 'Tu tiên']))
        ->assertOk()
        ->assertSee('Hành trình phương xa');
});

test('search never exposes unpublished stories and supports status and view sorting', function () {
    $popular = Story::factory()->create([
        'title' => 'Truyện phổ biến',
        'status_publish' => true,
        'status' => 'completed',
        'views' => 9000,
    ]);
    Story::factory()->create([
        'title' => 'Truyện riêng tư',
        'status_publish' => false,
        'status' => 'completed',
        'views' => 99999,
    ]);

    $this->get(route('search', ['status' => 'completed', 'sort' => 'most_viewed']))
        ->assertOk()
        ->assertSee('Truyện phổ biến')
        ->assertDontSee('Truyện riêng tư');

    expect($popular->fresh()->status_publish)->toBeTrue();
});

test('search treats percent underscore and escape characters as literal input', function () {
    Story::factory()->create([
        'title' => 'Tỷ lệ 100% hoàn thành',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'Tỷ lệ 1000 hoàn thành',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'Mã truyện chap_ter',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'Mã truyện chapter',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'Dấu ! đặc biệt',
        'status_publish' => true,
    ]);

    $this->get(route('search', ['q' => '100%']))
        ->assertOk()
        ->assertSee('Tỷ lệ 100% hoàn thành')
        ->assertDontSee('Tỷ lệ 1000 hoàn thành');

    $this->get(route('search', ['q' => 'chap_ter']))
        ->assertOk()
        ->assertSee('Mã truyện chap_ter')
        ->assertDontSee('Mã truyện chapter');

    $this->get(route('search', ['q' => '!']))
        ->assertOk()
        ->assertSee('Dấu ! đặc biệt');
});

test('top rated search excludes stories with fewer than five ratings', function () {
    Story::factory()->create([
        'title' => 'Điểm cao nhưng ít đánh giá',
        'status_publish' => true,
        'average_rating' => 5,
        'rating_count' => 4,
    ]);
    Story::factory()->create([
        'title' => 'Điểm tốt đủ đánh giá',
        'status_publish' => true,
        'average_rating' => 4.5,
        'rating_count' => 5,
    ]);

    $this->get(route('search', ['sort' => 'top_rated']))
        ->assertOk()
        ->assertSee('Điểm tốt đủ đánh giá')
        ->assertDontSee('Điểm cao nhưng ít đánh giá');
});

test('story detail recommends other published stories from matching categories', function () {
    $category = Category::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);
    $related = Story::factory()->create(['title' => 'Truyện cùng thể loại', 'status_publish' => true]);
    $unpublished = Story::factory()->create(['title' => 'Truyện chưa xuất bản', 'status_publish' => false]);

    $story->categories()->attach($category);
    $related->categories()->attach($category);
    $unpublished->categories()->attach($category);

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee('Truyện cùng thể loại')
        ->assertDontSee('Truyện chưa xuất bản');
});
