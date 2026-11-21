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
     * @param  int  $lockBudgetSeconds  tổng ngân sách chờ lock của cả job
     */
    public function __construct(
        public array $chapterIds,
        public int $waitSeconds = 60,
        public int $lockBudgetSeconds = 1200
    ) {}

    public function handle(): void
    {
        $locked = [];

        // Giới hạn tổng thời gian chờ lock: mỗi chương chờ tối đa
        // $waitSeconds mà một job có tới 100 chương thì tổng có thể
        // vượt $timeout (1500s) rồi bị worker kill giữa chừng, mất danh
        // sách chương kẹt (C3). Deadline = bắt đầu + ngân sách chờ lock
        // (mặc định 1200s, chừa 300s cho phần gửi thật); mỗi chương chỉ
        // chờ số giây còn lại tới deadline.
        $deadline = now()->addSeconds($this->lockBudgetSeconds);

        foreach (array_unique(array_map('intval', $this->chapterIds)) as $chapterId) {
            // Một chương không lấy được lock không được làm mất các chương
            // còn lại: gom lại rồi gửi bù sau 5 phút (J-M3).
            $waitSeconds = max(0, min($this->waitSeconds, $deadline->timestamp - now()->timestamp));

            if (! SendNewChapterNotifications::notifyChapter($chapterId, $waitSeconds)) {
                $locked[] = $chapterId;
            }
        }

        if ($locked !== []) {
            self::dispatch($locked, $this->waitSeconds, $this->lockBudgetSeconds)->delay(now()->addMinutes(5));
        }
    }
}
