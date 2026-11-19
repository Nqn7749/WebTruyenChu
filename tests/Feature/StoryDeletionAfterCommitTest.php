<?php

use App\Models\Story;
use App\Services\StoryDeletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('story cover deletion waits for the outer transaction to commit', function () {
    Storage::fake('public');
    Storage::disk('public')->put('covers/story.jpg', 'cover-image');

    $story = Story::factory()->create([
        'cover_image' => 'covers/story.jpg',
    ]);

    $service = app(StoryDeletionService::class);

    expect(fn () => DB::transaction(function () use ($service, $story): void {
        expect($service->delete($story, 'Test rollback'))->toBeTrue();

        throw new RuntimeException('Force outer transaction rollback.');
    }))->toThrow(RuntimeException::class);

    expect(Story::query()->whereKey($story->id)->exists())->toBeTrue();
    Storage::disk('public')->assertExists('covers/story.jpg');
});
