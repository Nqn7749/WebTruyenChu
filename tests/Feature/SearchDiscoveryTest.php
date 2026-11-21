<?php

use App\Models\Category;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

test('repeated identical searches are served from cache', function () {
    Story::factory()->create(['title' => 'Kiếm hiệp giang hồ', 'status_publish' => true]);

    $this->get(route('search', ['q' => 'kiem']))->assertOk();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->get(route('search', ['q' => 'kiem']))->assertOk();

    $storyQueries = collect(DB::getQueryLog())
        ->filter(fn ($entry) => str_contains($entry['query'], 'stories'))
        ->all();

    expect($storyQueries)->toBeEmpty();
});

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

test('relevance sorting prioritizes exact title then title prefix author and description matches', function () {
    Story::factory()->create([
        'title' => 'A story mentioning Ember later',
        'description' => 'An unrelated description.',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'Ember Rising',
        'description' => 'An unrelated description.',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'An unrelated title',
        'author_name' => 'Ember',
        'description' => 'An unrelated description.',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'Ember',
        'description' => 'An unrelated description.',
        'status_publish' => true,
    ]);
    Story::factory()->create([
        'title' => 'Another unrelated title',
        'description' => 'The word Ember appears in this description.',
        'status_publish' => true,
    ]);

    $response = $this->get(route('search', ['q' => 'Ember', 'sort' => 'relevance']));

    $response->assertOk()->assertViewHas('stories', function ($stories) {
        return $stories->pluck('title')->all() === [
            'Ember',
            'Ember Rising',
            'A story mentioning Ember later',
            'An unrelated title',
            'Another unrelated title',
        ];
    });
});

test('relevance sort is accepted without a keyword and falls back to newest ordering', function () {
    Story::factory()->create([
        'title' => 'Older published story',
        'status_publish' => true,
        'created_at' => now()->subDay(),
    ]);
    Story::factory()->create([
        'title' => 'Newer published story',
        'status_publish' => true,
        'created_at' => now(),
    ]);

    $this->get(route('search', ['sort' => 'relevance']))
        ->assertOk()
        ->assertSeeInOrder(['Newer published story', 'Older published story']);
});

test('relevance sorting keeps author prefix and description ahead of category and tag matches', function () {
    $category = Category::factory()->create(['name' => 'Ember fantasy']);
    $authorMatch = Story::factory()->create([
        'title' => 'An unrelated title',
        'author_name' => 'Ember Writer',
        'description' => 'No matching description.',
        'status_publish' => true,
    ]);
    $descriptionMatch = Story::factory()->create([
        'title' => 'Another unrelated title',
        'author_name' => 'Different author',
        'description' => 'An Ember adventure.',
        'status_publish' => true,
    ]);
    $categoryMatch = Story::factory()->create([
        'title' => 'A category-only result',
        'author_name' => 'Different author',
        'description' => 'No matching description.',
        'status_publish' => true,
    ]);
    $categoryMatch->categories()->attach($category);

    $tagMatch = Story::factory()->create([
        'title' => 'A tag-only result',
        'author_name' => 'Different author',
        'description' => 'No matching description.',
        'status_publish' => true,
    ]);
    $tagMatch->tags()->create(['name' => 'Ember tag', 'slug' => 'ember-tag']);

    $response = $this->get(route('search', ['q' => 'Ember', 'sort' => 'relevance']));

    $response->assertOk()->assertViewHas('stories', function ($stories) use ($authorMatch, $descriptionMatch, $categoryMatch, $tagMatch) {
        $ids = $stories->pluck('id')->all();

        return array_search($authorMatch->id, $ids, true) < array_search($descriptionMatch->id, $ids, true)
            && array_search($descriptionMatch->id, $ids, true) < array_search($categoryMatch->id, $ids, true)
            && in_array($tagMatch->id, $ids, true);
    });
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

test('an array search parameter does not crash the navigation bar', function () {
    // ?q[]=x khiến request('q') thành mảng: trước đây e() trên mảng throw
    // TypeError làm 500 mọi trang public có navbar.
    $this->get('/?q[]=x')->assertOk();
});
