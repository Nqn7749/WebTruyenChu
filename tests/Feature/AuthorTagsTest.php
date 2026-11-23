<?php

use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Support\TagInput;
use Illuminate\Support\Facades\Cache;

function authorStoryPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Truyện có tag',
        'description' => 'Mô tả.',
        'status' => 'ongoing',
        'categories' => [Category::factory()->create()->id],
    ], $overrides);
}

test('tag input trims, strips hashes and angle brackets and collapses duplicates', function () {
    $tags = TagInput::parse(" #Hệ Thống , hệ thống;  Trọng  Sinh\n <script> , !!! ");

    expect($tags)->toHaveKeys(['he-thong', 'trong-sinh'])
        ->and($tags['he-thong'])->toBe('Hệ Thống')
        ->and($tags['trong-sinh'])->toBe('Trọng Sinh')
        ->and(array_keys($tags))->not->toContain('');
});

test('an author can type new tags when posting a story and they are created and attached', function () {
    $author = User::factory()->create(['status' => true]);

    $this->actingAs($author)
        ->post(route('writer.stories.store'), authorStoryPayload(['tags' => 'Hệ Thống, trọng sinh , Báo Thù']))
        ->assertRedirect(route('writer.stories.index'));

    $story = Story::query()->where('title', 'Truyện có tag')->firstOrFail();

    expect($story->tags()->pluck('name')->sort()->values()->all())->toBe(['Báo Thù', 'Hệ Thống', 'trọng sinh'])
        ->and(Tag::query()->count())->toBe(3);
});

test('typing an existing tag in another case reuses it instead of creating a duplicate', function () {
    Tag::factory()->create(['name' => 'Hệ Thống', 'slug' => 'he-thong']);
    $author = User::factory()->create(['status' => true]);

    $this->actingAs($author)
        ->post(route('writer.stories.store'), authorStoryPayload(['tags' => 'hệ thống']))
        ->assertRedirect(route('writer.stories.index'));

    expect(Tag::query()->count())->toBe(1)
        ->and(Story::query()->firstOrFail()->tags()->count())->toBe(1);
});

test('editing a story replaces its tags and shows them in the form', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $category = Category::factory()->create();
    $story->categories()->sync([$category->id]);
    $story->tags()->sync([Tag::factory()->create(['name' => 'Cũ', 'slug' => 'cu'])->id]);

    $this->actingAs($author)
        ->get(route('writer.stories.edit', $story))
        ->assertOk()
        ->assertSee('value="Cũ"', false);

    $this->actingAs($author)
        ->put(route('writer.stories.update', $story), [
            'title' => $story->title,
            'status' => 'ongoing',
            'categories' => [$category->id],
            'tags' => 'Mới, Hay',
        ])
        ->assertRedirect(route('writer.stories.index'));

    expect($story->tags()->pluck('name')->sort()->values()->all())->toBe(['Hay', 'Mới']);
});

test('clearing the tag field removes all tags from the story', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $category = Category::factory()->create();
    $story->categories()->sync([$category->id]);
    $story->tags()->sync([Tag::factory()->create()->id]);

    $this->actingAs($author)
        ->put(route('writer.stories.update', $story), [
            'title' => $story->title,
            'status' => 'ongoing',
            'categories' => [$category->id],
            'tags' => '',
        ])
        ->assertRedirect(route('writer.stories.index'));

    expect($story->tags()->count())->toBe(0);
});

test('more than ten tags or a tag longer than thirty characters is rejected', function () {
    $author = User::factory()->create(['status' => true]);

    $this->actingAs($author)
        ->post(route('writer.stories.store'), authorStoryPayload(['tags' => implode(',', range(1, 11))]))
        ->assertSessionHasErrors('tags');

    $this->actingAs($author)
        ->post(route('writer.stories.store'), authorStoryPayload(['tags' => str_repeat('a', 31)]))
        ->assertSessionHasErrors('tags');

    expect(Tag::query()->count())->toBe(0);
});

test('the legacy tags array payload is still accepted', function () {
    $author = User::factory()->create(['status' => true]);

    $this->actingAs($author)
        ->post(route('writer.stories.store'), authorStoryPayload(['tags' => ['Một', 'Hai']]))
        ->assertSessionHasNoErrors();

    expect(Story::query()->firstOrFail()->tags()->count())->toBe(2);
});

test('tags on the story page link to a tag search and tag HTML is not rendered', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $tag = Tag::factory()->create(['name' => 'Hệ Thống', 'slug' => 'he-thong']);
    $story->tags()->sync([$tag->id]);

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee(route('search', ['tag' => 'he-thong']), false)
        ->assertSee('#Hệ Thống');
});

test('searching by tag only returns stories with that tag and shows the active tag', function () {
    Cache::flush();
    $tag = Tag::factory()->create(['name' => 'Hệ Thống', 'slug' => 'he-thong']);
    $tagged = Story::factory()->create(['status_publish' => true, 'title' => 'Truyện gắn tag riêng']);
    $tagged->tags()->sync([$tag->id]);
    $draftTagged = Story::factory()->create(['status_publish' => false, 'title' => 'Truyện nháp gắn tag']);
    $draftTagged->tags()->sync([$tag->id]);
    Story::factory()->create(['status_publish' => true, 'title' => 'Truyện không tag']);

    $this->get(route('search', ['tag' => 'he-thong']))
        ->assertOk()
        ->assertSee('Truyện gắn tag riêng')
        ->assertDontSee('Truyện không tag')
        ->assertDontSee('Truyện nháp gắn tag')
        ->assertSee('Tag: #Hệ Thống')
        ->assertSee('Bỏ lọc tag');

    $this->get(route('search', ['tag' => 'khong-ton-tai']))
        ->assertOk()
        ->assertDontSee('Truyện gắn tag riêng');
});
