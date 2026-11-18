<?php

use App\Models\Category;
use App\Models\Story;

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
