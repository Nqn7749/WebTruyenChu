<?php

use App\Models\Chapter;
use App\Models\Story;

test('chapter sitemap contains canonical URLs for published chapters only', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $publishedChapter = Chapter::factory()->for($story)->create([
        'slug' => 'chuong-cong-khai',
        'status' => true,
    ]);
    Chapter::factory()->for($story)->create([
        'slug' => 'chuong-ban-nhap',
        'status' => false,
    ]);

    $unpublishedStory = Story::factory()->create(['status_publish' => false]);
    Chapter::factory()->for($unpublishedStory)->create([
        'slug' => 'chuong-truyen-chua-dang',
        'status' => true,
    ]);

    $this->get(route('sitemap.index'))
        ->assertOk()
        ->assertSee(route('sitemap.chapters', ['page' => 1]));

    $this->get(route('sitemap.chapters', ['page' => 1]))
        ->assertOk()
        ->assertSee(route('chapters.show', [$story, $publishedChapter->slug]))
        ->assertDontSee('chuong-ban-nhap')
        ->assertDontSee('chuong-truyen-chua-dang');
});
