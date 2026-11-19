<?php

use App\Models\Chapter;
use App\Models\Story;

test('chapter reader exposes accessibility and focus controls', function () {
    $story = Story::factory()->create([
        'status_publish' => true,
    ]);

    $chapter = Chapter::factory()->for($story)->create([
        'status' => true,
    ]);

    $this->get(route('chapters.show', [$story, $chapter->slug]))
        ->assertOk()
        ->assertSee('id="fontDecrease"', false)
        ->assertSee('id="fontIncrease"', false)
        ->assertSee('id="lineHeightDecrease"', false)
        ->assertSee('id="lineHeightIncrease"', false)
        ->assertSee('id="focusModeToggle"', false)
        ->assertSee('data-theme="sepia"', false)
        ->assertSee('data-theme="dark"', false);
});
