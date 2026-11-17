<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Services\StoryDeletionService;
use Illuminate\Console\Command;

class DeleteStoriesWithoutChapters extends Command
{
    protected $signature = 'stories:delete-without-chapters';

    protected $description = 'Soft-delete stories that have had no chapters for 30 days';

    public function handle(StoryDeletionService $deletion): int
    {
        $cutoff = now()->subDays(30);
        $deletedCount = 0;

        Story::query()
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('chapters')
            ->orderBy('id')
            ->chunkById(100, function ($stories) use ($deletion, &$deletedCount) {
                foreach ($stories as $story) {
                    $deleted = $deletion->delete(
                        $story,
                        'Tự động xóa: truyện không có chương trong vòng 30 ngày kể từ ngày đăng.',
                        null,
                        true
                    );

                    if ($deleted) {
                        $deletedCount++;
                    }
                }
            });

        $this->info("Đã tự động xóa {$deletedCount} truyện không có chương.");

        return self::SUCCESS;
    }
}
