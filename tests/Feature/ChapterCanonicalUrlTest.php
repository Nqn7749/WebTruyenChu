<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;

test('published chapters use unique slug URLs and support duplicate chapter numbers', function () {
    $story = Story::factory()->create(['status_publish' => true]);

    $first = Chapter::factory()->for($story)->create([
        'chapter_number' => 5,
        'slug' => 'chuong-nam-ban-a',
        'status' => true,
    ]);

    $second = Chapter::factory()->for($story)->create([
        'chapter_number' => 5,
        'slug' => 'chuong-nam-ban-b',
        'status' => true,
    ]);

    $this->get(route('chapters.show', [$story, $first->slug]))
        ->assertOk()
        ->assertViewHas('nextChapter', fn ($chapter) => $chapter?->is($second));

    $this->get(route('chapters.show', [$story, $second->slug]))
        ->assertOk()
        ->assertViewHas('prevChapter', fn ($chapter) => $chapter?->is($first));
});

test('legacy chapter number URLs permanently redirect to the first published matching chapter', function () {
    $story = Story::factory()->create(['status_publish' => true]);

    $first = Chapter::factory()->for($story)->create([
        'chapter_number' => 3,
        'slug' => 'chuong-ba-a',
        'status' => true,
    ]);

    Chapter::factory()->for($story)->create([
        'chapter_number' => 3,
        'slug' => 'chuong-ba-b',
        'status' => true,
    ]);

    $this->get(route('chapters.legacy', [$story, 3]))
        ->assertStatus(301)
        ->assertRedirect(route('chapters.show', [$story, $first->slug]));
});

test('chapter slug binding is scoped to its parent story', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $otherStory = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($otherStory)->create([
        'slug' => 'private-to-another-story',
        'status' => true,
    ]);

    $this->get(route('chapters.show', [$story, $chapter->slug]))
        ->assertNotFound();
});

test('writers can publish two chapters with the same chapter number', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create([
        'user_id' => $author->id,
        'status_publish' => true,
    ]);

    $this->actingAs($author)
        ->post(route('writer.stories.chapters.store', $story), [
            'chapter_number' => 8,
            'title' => 'Bản dịch thứ nhất',
            'content' => 'Nội dung thứ nhất.',
            'status' => 1,
        ])
        ->assertRedirect();

    $this->actingAs($author)
        ->post(route('writer.stories.chapters.store', $story), [
            'chapter_number' => 8,
            'title' => 'Bản dịch thứ hai',
            'content' => 'Nội dung thứ hai.',
            'status' => 1,
        ])
        ->assertRedirect();

    expect($story->chapters()->where('chapter_number', 8)->count())->toBe(2)
        ->and($story->chapters()->where('chapter_number', 8)->pluck('slug')->unique()->count())->toBe(2);
});
