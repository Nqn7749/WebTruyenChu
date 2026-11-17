<?php

use App\Models\Chapter;
use App\Models\ReadingHistory;
use App\Models\Story;
use App\Models\User;

test('reading progress cannot be overwritten by a delayed request from another chapter', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);
    $firstChapter = Chapter::factory()->for($story)->create(['chapter_number' => 1]);
    $secondChapter = Chapter::factory()->for($story)->create(['chapter_number' => 2]);

    ReadingHistory::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'chapter_id' => $secondChapter->id,
        'read_at' => now(),
        'scroll_percent' => 35,
    ]);

    $this->actingAs($user)
        ->patchJson(route('reading-history.update-progress'), [
            'story_id' => $story->id,
            'chapter_id' => $firstChapter->id,
            'scroll_percent' => 90,
        ])
        ->assertStatus(409)
        ->assertJson(['status' => 'stale']);

    expect(ReadingHistory::first()->scroll_percent)->toBe(35);
    expect(ReadingHistory::first()->chapter_id)->toBe($secondChapter->id);
});

test('reading progress accepts repeated identical percentages for the active chapter', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create();

    ReadingHistory::create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'chapter_id' => $chapter->id,
        'read_at' => now(),
        'scroll_percent' => 45,
    ]);

    $this->actingAs($user)
        ->patchJson(route('reading-history.update-progress'), [
            'story_id' => $story->id,
            'chapter_id' => $chapter->id,
            'scroll_percent' => 45,
        ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});
