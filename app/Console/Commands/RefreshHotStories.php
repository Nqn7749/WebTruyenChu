<?php

namespace App\Console\Commands;

use App\Services\HotStoriesService;
use Illuminate\Console\Command;

class RefreshHotStories extends Command
{
    protected $signature = 'stories:refresh-hot';

    protected $description = 'Mark the most active stories of the last days as hot (admin-pinned stories are left alone)';

    public function handle(HotStoriesService $hot): int
    {
        $result = $hot->refresh();

        $this->info('Hot stories updated: '.$result['marked'].' marked, '.$result['unmarked'].' unmarked.');

        return self::SUCCESS;
    }
}
