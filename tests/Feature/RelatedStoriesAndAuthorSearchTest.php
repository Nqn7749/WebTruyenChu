<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function storyWith(array $attributes = [], array $categories = [], array $tags = []): Story
{
    $story = Story::factory()->create(array_merge(['status_publish' => true], $attributes));
    $story->categories()->sync(collect($categories)->pluck('id')->all());
    $story->tags()->sync(collect($tags)->pluck('id')->all());

    return $story;
}

beforeEach(function () {
    Cache::flush();
});

test('related stories are limited to ten and exclude the story itself and unpublished ones', function () {
    $category = Category::factory()->create();
    $story = storyWith([], [$category]);
    foreach (range(1, 12) as $i) {
        storyWith(['title' => 'Cùng thể loại '.$i], [$category]);
    }
    $draft = storyWith(['status_publish' => false, 'title' => 'Bản nháp liên quan'], [$category]);

    $related = Story::relatedTo($story->load(['categories', 'tags']), 10);

    expect($related)->toHaveCount(10)
        ->and($related->pluck('id'))->not->toContain($story->id)
        ->and($related->pluck('id'))->not->toContain($draft->id);
});

test('related stories rank shared genres, tags and the same author first', function () {
    [$cat1, $cat2, $cat3] = [Category::factory()->create(), Category::factory()->create(), Category::factory()->create()];
    $tag = Tag::factory()->create();
    $story = storyWith(['author_name' => 'Nguyễn Văn A'], [$cat1, $cat2], [$tag]);

    $oneGenre = storyWith(['title' => 'Một thể loại', 'views' => 5000], [$cat1]);
    $twoGenres = storyWith(['title' => 'Hai thể loại', 'views' => 1], [$cat1, $cat2]);
    $genreAndTag = storyWith(['title' => 'Thể loại và tag', 'views' => 1], [$cat1, $cat2], [$tag]);
    $sameAuthorOnly = storyWith(['title' => 'Cùng tác giả', 'author_name' => 'Nguyễn Văn A', 'views' => 1], [$cat3]);
    $unrelated = storyWith(['title' => 'Không liên quan'], [$cat3]);

    $related = Story::relatedTo($story->load(['categories', 'tags']), 10);
    $titles = $related->pluck('title')->all();

    // genre x3 + tag x2: 2 genres + tag = 8; 2 genres = 6; same author = 4; 1 genre = 3.
    expect($titles)->toBe(['Thể loại và tag', 'Hai thể loại', 'Cùng tác giả', 'Một thể loại'])
        ->and($titles)->not->toContain('Không liên quan');
});

test('a story with no genre, tag or author has no related stories', function () {
    $story = storyWith(['author_name' => null]);
    storyWith();

    expect(Story::relatedTo($story->load(['categories', 'tags']), 10))->toBeEmpty();
});

test('the story page shows up to ten related stories under a related heading', function () {
    $category = Category::factory()->create();
    $story = storyWith([], [$category]);
    foreach (range(1, 12) as $i) {
        storyWith(['title' => 'Liên quan số '.$i], [$category]);
    }

    $html = $this->get(route('stories.show', $story))->assertOk()->assertSee('Truyện liên quan')->getContent();

    expect(substr_count($html, 'story-card-item'))->toBe(10);
});

test('the author name on the story page links to a search for that author', function () {
    $story = storyWith(['author_name' => 'Tô Hoài']);

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee(route('search', ['author' => 'Tô Hoài']), false);
});

test('the name of whoever posted the story links to a search for that poster', function () {
    $poster = User::factory()->create(['name' => 'Người Đăng Truyện']);
    $story = storyWith(['user_id' => $poster->id]);

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee('Người đăng:')
        ->assertSee('Người Đăng Truyện')
        ->assertSee(route('search', ['poster' => $poster->id]), false);
});

test('searching by author is an exact filter that returns only that authors published stories', function () {
    storyWith(['title' => 'Truyện của Tô Hoài', 'author_name' => 'Tô Hoài']);
    storyWith(['title' => 'Truyện nháp của Tô Hoài', 'author_name' => 'Tô Hoài', 'status_publish' => false]);
    storyWith(['title' => 'Truyện của Tô Hoài Nam', 'author_name' => 'Tô Hoài Nam']);
    storyWith(['title' => 'Truyện người khác', 'author_name' => 'Người khác']);

    $this->get(route('search', ['author' => 'Tô Hoài']))
        ->assertOk()
        ->assertSee('Truyện của Tô Hoài')
        ->assertDontSee('Truyện nháp của Tô Hoài')
        ->assertDontSee('Truyện của Tô Hoài Nam')
        ->assertDontSee('Truyện người khác')
        ->assertSee('Tác giả: Tô Hoài')
        ->assertSee('Bỏ lọc tác giả');
});

test('searching by poster returns the stories that user posted and shows the poster chip', function () {
    $poster = User::factory()->create(['name' => 'Bạn Đăng Bài']);
    $other = User::factory()->create();
    storyWith(['title' => 'Do bạn đăng', 'user_id' => $poster->id]);
    storyWith(['title' => 'Do người khác đăng', 'user_id' => $other->id]);

    $this->get(route('search', ['poster' => $poster->id]))
        ->assertOk()
        ->assertSee('Do bạn đăng')
        ->assertDontSee('Do người khác đăng')
        ->assertSee('Người đăng: Bạn Đăng Bài')
        ->assertSee('Bỏ lọc người đăng');
});

test('author, poster and tag filters combine and each chip removes only its own filter', function () {
    $poster = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Hệ Thống', 'slug' => 'he-thong']);
    storyWith(['title' => 'Khớp cả ba', 'author_name' => 'Tô Hoài', 'user_id' => $poster->id], [], [$tag]);
    storyWith(['title' => 'Thiếu tag', 'author_name' => 'Tô Hoài', 'user_id' => $poster->id]);

    $response = $this->get(route('search', ['author' => 'Tô Hoài', 'poster' => $poster->id, 'tag' => 'he-thong']))
        ->assertOk()
        ->assertSee('Khớp cả ba')
        ->assertDontSee('Thiếu tag');

    // Removing the tag chip keeps the author and poster filters in its link.
    $withoutTag = route('search', ['author' => 'Tô Hoài', 'poster' => $poster->id]);
    $response->assertSee(htmlspecialchars($withoutTag, ENT_QUOTES), false);
});

test('an invalid poster value is rejected and an unknown author returns nothing', function () {
    storyWith(['title' => 'Có đó']);

    $this->get(route('search', ['poster' => 'abc']))->assertSessionHasErrors('poster');

    $this->get(route('search', ['author' => 'Không có ai']))->assertOk()->assertDontSee('Có đó');
});

test('author and poster searches are cached separately', function () {
    storyWith(['title' => 'Của tác giả Một', 'author_name' => 'Một']);
    storyWith(['title' => 'Của tác giả Hai', 'author_name' => 'Hai']);

    $this->get(route('search', ['author' => 'Một']))->assertSee('Của tác giả Một')->assertDontSee('Của tác giả Hai');
    $this->get(route('search', ['author' => 'Hai']))->assertSee('Của tác giả Hai')->assertDontSee('Của tác giả Một');
});
