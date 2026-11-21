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
     * Số lần thử lại khi gửi lỗi giữa chừng (xem retry_after 1800s ở
     * config/queue.php). Kèm dedup ở từng chương để retry không gửi trùng.
     */
    public int $tries = 3;

    public array $backoff = [60, 300];

    /**
     * Thời gian tối đa job được chạy (giây): phải nhỏ hơn retry_after
     * của queue (1800s) để queue không release job rồi chạy trùng trong
     * khi job cũ vẫn đang gửi (J-M2). Gửi hàng loạt nhiều chương nên
     * lâu hơn job gửi một chương.
     */
    public int $timeout = 1500;

    /**
     * @param  array<int, int>  $chapterIds
     * @param  int  $waitSeconds  thời gian tối đa chờ lock của mỗi chương
     */
    public function __construct(
        public array $chapterIds,
        public int $waitSeconds = 60
    ) {}

    public function handle(): void
    {
        $locked = [];

        foreach (array_unique(array_map('intval', $this->chapterIds)) as $chapterId) {
            // Một chương không lấy được lock không được làm mất các chương
            // còn lại: gom lại rồi gửi bù sau 5 phút (J-M3).
            if (! SendNewChapterNotifications::notifyChapter($chapterId, $this->waitSeconds)) {
                $locked[] = $chapterId;
            }
        }

        if ($locked !== []) {
            self::dispatch($locked, $this->waitSeconds)->delay(now()->addMinutes(5));
        }
    }
}
