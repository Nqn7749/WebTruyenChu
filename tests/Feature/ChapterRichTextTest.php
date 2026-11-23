<?php

use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use App\Support\ChapterContent;

test('sanitizer keeps word-like formatting', function () {
    $html = '<p class="ql-align-center"><strong>Đậm</strong> <em>nghiêng</em> <u>gạch</u> <s>ngang</s> '
        .'<span style="color: rgb(230, 0, 0); background-color: rgb(255, 255, 0);">màu</span> '
        .'<span class="ql-size-large">to</span></p><h2>Tiêu đề</h2><blockquote>Trích dẫn</blockquote>'
        .'<ol><li>Một</li><li class="ql-indent-1">Hai</li></ol><ul><li>Chấm</li></ul><p>x<sub>2</sub> y<sup>3</sup></p>'
        .'<p><a href="https://example.com/a" title="t">liên kết</a></p>';

    $clean = ChapterContent::sanitize($html);

    expect($clean)
        ->toContain('<strong>Đậm</strong>')
        ->toContain('<em>nghiêng</em>')
        ->toContain('<u>gạch</u>')
        ->toContain('ql-align-center')
        ->toContain('color:')
        ->toContain('ql-size-large')
        ->toContain('<h2>Tiêu đề</h2>')
        ->toContain('<blockquote>Trích dẫn</blockquote>')
        ->toContain('<ol>')
        ->toContain('<ul>')
        ->toContain('<sub>2</sub>')
        ->toContain('href="https://example.com/a"');
});

test('sanitizer removes scripts, event handlers, dangerous urls and unknown styles', function (string $payload, array $forbidden) {
    $clean = ChapterContent::sanitize('<p>ok</p>'.$payload);

    expect($clean)->toContain('ok');
    foreach ($forbidden as $needle) {
        expect(strtolower($clean))->not->toContain($needle);
    }
})->with([
    'script tag' => ['<script>alert(1)</script>', ['<script', 'alert(1)']],
    'img onerror' => ['<img src=x onerror=alert(1)>', ['<img', 'onerror']],
    'onclick on p' => ['<p onclick="alert(1)">x</p>', ['onclick']],
    'javascript link' => ['<a href="javascript:alert(1)">x</a>', ['javascript:']],
    'data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:']],
    'iframe' => ['<iframe src="https://evil.example"></iframe>', ['<iframe']],
    'style url and position' => ['<span style="background:url(javascript:alert(1));position:fixed">x</span>', ['javascript', 'position', 'url(']],
    'svg' => ['<svg onload=alert(1)></svg>', ['<svg', 'onload']],
    'unknown class' => ['<p class="admin-only">x</p>', ['admin-only']],
]);

test('external links get rel nofollow and open in a new tab safely', function () {
    $clean = ChapterContent::sanitize('<p><a href="https://example.com">x</a></p>');

    expect($clean)->toContain('nofollow')->toContain('target="_blank"');
});

test('legacy plain text is escaped and keeps its paragraphs and line breaks', function () {
    $html = ChapterContent::toHtml("Dòng một\nDòng hai\n\nĐoạn hai & còn nữa");

    expect($html)
        ->toContain('<p>Dòng một<br>')
        ->toContain('Dòng hai</p>')
        ->toContain('Đoạn hai &amp; còn nữa');
});

test('plain text with a stray angle bracket is not treated as html', function () {
    expect(ChapterContent::toHtml('1 < 2 và 3 > 2'))->toBe('<p>1 &lt; 2 và 3 &gt; 2</p>');
});

test('chapter content is sanitized when saved, whatever the entry point', function () {
    $story = Story::factory()->create(['status_publish' => true]);

    $chapter = new Chapter;
    $chapter->forceFill([
        'story_id' => $story->id,
        'chapter_number' => 1,
        'title' => 'T',
        'slug' => 'chuong-1-abc',
        'content' => '<p>Xin chào</p><script>alert(1)</script>',
        'status' => true,
    ])->save();

    expect($chapter->fresh()->content)->toContain('Xin chào')->not->toContain('<script');
});

test('the reader page renders rich text and never raw scripts', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 1]);
    // Bypass the mutator to simulate a row stored before sanitizing existed.
    Chapter::query()->whereKey($chapter->id)->update([
        'content' => '<p><strong>Đậm hiển thị</strong></p><script>window.pwned=1</script>',
    ]);

    $this->get(route('chapters.show', [$story, $chapter->slug]))
        ->assertOk()
        ->assertSee('<strong>Đậm hiển thị</strong>', false)
        ->assertDontSee('window.pwned', false);
});

test('the reader page shows imported html paragraphs as paragraphs, not literal tags', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 1]);
    Chapter::query()->whereKey($chapter->id)->update(['content' => "<p>Đoạn một</p>\n<p>Đoạn hai</p>"]);

    $this->get(route('chapters.show', [$story, $chapter->slug]))
        ->assertOk()
        ->assertSee('<p>Đoạn một</p>', false)
        ->assertDontSee('&lt;p&gt;', false);
});

test('a writer can save rich text and an empty editor body is rejected', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);

    $this->actingAs($author)
        ->post(route('writer.stories.chapters.store', $story), [
            'chapter_number' => 1,
            'title' => 'Có định dạng',
            'content' => '<p><strong>Đậm</strong> <span style="color: rgb(230, 0, 0);">đỏ</span></p><script>x()</script>',
            'status' => 1,
        ])
        ->assertSessionHasNoErrors();

    $saved = Chapter::query()->where('story_id', $story->id)->firstOrFail();
    expect($saved->content)->toContain('<strong>Đậm</strong>')->not->toContain('<script');

    $this->actingAs($author)
        ->post(route('writer.stories.chapters.store', $story), [
            'chapter_number' => 2,
            'title' => 'Trống',
            'content' => '<p><br></p>',
            'status' => 1,
        ])
        ->assertSessionHasErrors('content');
});

test('the chapter form loads the rich text editor with the stored content', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['content' => '<p><em>Nội dung cũ</em></p>']);

    $this->actingAs($author)
        ->get(route('writer.chapters.edit', $chapter))
        ->assertOk()
        ->assertSee('chapter-toolbar', false)
        ->assertSee('<em>Nội dung cũ</em>', false);
});
