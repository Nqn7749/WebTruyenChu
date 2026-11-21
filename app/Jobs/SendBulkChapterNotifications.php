<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBulkChapterNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần thử lại khi gửi lỗi giữa chừng (xem retry_after 90s ở
     * config/queue.php). Kèm dedup ở từng chương để retry không gửi trùng.
     */
    public int $tries = 3;

    public array $backoff = [60, 300];

    /**
     * @param  array<int, int>  $chapterIds
     */
    public function __construct(
        public array $chapterIds
    ) {}

    public function handle(): void
    {
        foreach (array_unique(array_map('intval', $this->chapterIds)) as $chapterId) {
            SendNewChapterNotifications::notifyChapter($chapterId);
        }
    }
}
