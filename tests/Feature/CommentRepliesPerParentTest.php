<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;

test('each parent comment shows up to twenty replies on the story page', function () {
    $story = Story::factory()->create(['status_publish' => true]);

    $busyParent = Comment::factory()->for($story)->create(['content' => 'Cha bận rộn.']);
    foreach (range(1, 25) as $i) {
        Comment::factory()->for($story)->create([
            'parent_id' => $busyParent->id,
            'content' => 'Reply bận rộn #'.$i,
        ]);
    }

    $quietParent = Comment::factory()->for($story)->create(['content' => 'Cha yên tĩnh.']);
    foreach (range(1, 5) as $i) {
        Comment::factory()->for($story)->create([
            'parent_id' => $quietParent->id,
            'content' => 'Reply yên tĩnh #'.$i,
        ]);
    }

    $response = $this->get(route('stories.show', $story));

    $response->assertOk();
    $response->assertSee('Cha bận rộn.');
    $response->assertSee('Cha yên tĩnh.');
    $response->assertSee('Reply bận rộn #1');
    $response->assertSee('Reply bận rộn #20');
    $response->assertDontSee('Reply bận rộn #21');
    $response->assertDontSee('Reply bận rộn #25');
    $response->assertSee('Reply yên tĩnh #5');
});

test('each parent comment shows up to twenty replies on the chapter page', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    $busyParent = Comment::factory()->for($story)->create([
        'chapter_id' => $chapter->id,
        'content' => 'Cha bận rộn chương.',
    ]);
    foreach (range(1, 25) as $i) {
        Comment::factory()->for($story)->create([
            'chapter_id' => $chapter->id,
            'parent_id' => $busyParent->id,
            'content' => 'Reply chương #'.$i,
        ]);
    }

    $response = $this->get(route('chapters.show', [$story, $chapter]));

    $response->assertOk();
    $response->assertSee('Cha bận rộn chương.');
    $response->assertSee('Reply chương #20');
    $response->assertDontSee('Reply chương #21');
});
