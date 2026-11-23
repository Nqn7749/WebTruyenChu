<?php

use App\Models\Chapter;
use App\Models\Story;

test('meta description is escaped exactly once for quotes, ampersands and angle brackets', function () {
    $story = Story::factory()->create(['status_publish' => true, 'title' => 'Tom & Jerry']);
    $chapter = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 1]);
    Chapter::query()->whereKey($chapter->id)->update(['content' => '<p>Anh nói: "Em ơi" & cười 1 < 2.</p>']);

    $html = $this->get(route('chapters.show', [$story, $chapter->slug]))->assertOk()->getContent();

    // One level of escaping in the attribute: &quot; and &amp;, never &amp;quot; or &amp;amp;.
    expect($html)
        ->toContain('<meta name="description" content="Anh nói: &quot;Em ơi&quot; &amp; cười 1 &lt; 2.')
        ->toContain('<meta property="og:description" content="Anh nói: &quot;Em ơi&quot; &amp; cười 1 &lt; 2.')
        ->not->toContain('&amp;quot;')
        ->not->toContain('&amp;amp;')
        ->not->toContain('&amp;lt;');
});

test('page title and og title are escaped once', function () {
    $story = Story::factory()->create(['status_publish' => true, 'title' => 'Tom & "Jerry"']);

    $html = $this->get(route('stories.show', $story))->assertOk()->getContent();

    expect($html)
        ->toContain('Tom &amp; &quot;Jerry&quot;')
        ->not->toContain('&amp;amp;')
        ->not->toContain('&amp;quot;');
});

test('meta tags cannot be broken out of with quotes or tags in user supplied text', function () {
    $story = Story::factory()->create(['status_publish' => true, 'title' => 'x', 'description' => '"><script>alert(1)</script><meta x="']);

    $html = $this->get(route('stories.show', $story))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('"><script>');
});

test('pages without a custom description fall back to the default text', function () {
    $this->get('/')->assertOk()->assertSee('<meta name="description" content="', false);
});
