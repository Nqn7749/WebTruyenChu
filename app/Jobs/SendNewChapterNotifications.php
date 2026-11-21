<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendNewChapterNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần thử lại khi gửi lỗi giữa chừng (xem retry_after 1800s ở
     * config/queue.php). Kèm dedup ở notifyChapter() để retry không gửi trùng.
     */
    public int $tries = 3;

    public array $backoff = [60, 300];

    /**
     * Thời gian tối đa job được chạy (giây): phải nhỏ hơn retry_after
     * của queue (1800s) để queue không release job rồi chạy trùng trong
     * khi job cũ vẫn đang gửi (J-M2).
     */
    public int $timeout = 900;

    public function __construct(
        public int $chapterId
    ) {}

    public function handle(): void
    {
        if (! self::notifyChapter($this->chapterId)) {
            // Không lấy được lock trong thời gian chờ: một job khác đang
            // gửi cho chương này. Đưa job trở lại hàng đợi sau 60 giây
            // mà không tốn lượt tries (thay vì fail rồi retry) — thông
            // báo không bị mất (J-M3). Khi chạy trực tiếp (test, console)
            // không có job để release nên chỉ bỏ qua.
            if ($this->job !== null) {
                $this->release(60);
            }
        }
    }

    /**
     * Gửi thông báo chương mới cho người theo dõi truyện. Tách thành
     * static để job gửi hàng loạt (SendBulkChapterNotifications) dùng
     * chung mà không phải dispatch 1 job cho mỗi chương (N-L5).
     *
     * Trả về false khi không lấy được lock trong $waitSeconds giây
     * (caller tự quyết định thử lại sau); true khi đã xử lý xong
     * (đã gửi, hoặc bỏ qua vì chương/truyện không còn công khai).
     */
    public static function notifyChapter(int $chapterId, int $waitSeconds = 60): bool
    {
        try {
            // Khóa theo chương để 2 job cùng chương chạy song song không
            // gửi trùng. Lock TTL 1200s dài hơn $timeout (900s) để lock
            // không hết hạn giữa chừng khi gửi cho nhiều người theo dõi
            // (J-M2). Bảng cache_locks đã có sẵn trong migration tạo
            // bảng cache.
            return Cache::lock('notify-chapter-'.$chapterId, 1200)->block($waitSeconds, function () use ($chapterId) {
                // Đọc lại trạng thái TRONG lock: chương có thể đã bị gỡ
                // công khai trong lúc job chờ lock, gửi theo trạng thái
                // đọc trước lock là gửi thông báo cho chương không còn
                // công khai (J-M1).
                $chapter = Chapter::query()
                    ->with('story')
                    ->find($chapterId);

                if (! $chapter || ! $chapter->status || ! $chapter->story?->status_publish) {
                    return true;
                }

                User::query()
                    // Tài khoản bị vô hiệu hóa không đăng nhập được, không gửi
                    // thông báo để khỏi tích lũy notification không ai đọc (NL-3).
                    ->where('status', true)
                    // Tác giả không nhận thông báo chương của chính mình: trước
                    // đây chỉ được chặn gián tiếp bởi FavoriteController cấm
                    // tác giả tự favorite, row favorite của tác giả (seed, sửa
                    // DB tay) vẫn làm tác giả nhận thông báo (NL-3).
                    ->when($chapter->story->user_id, fn ($query, $authorId) => $query->whereKeyNot($authorId))
                    ->whereHas('favorites', function ($query) use ($chapter) {
                        $query->where('story_id', $chapter->story_id)
                            ->where('notify_new_chapter', true);
                    })
                    ->chunkById(200, function ($users) use ($chapter) {
                        // Idempotency: bỏ qua người đã có thông báo cho chương này
                        // (job retry sau khi fail giữa batch sẽ không gửi trùng).
                        $alreadyNotified = DB::table('notifications')
                            ->where('notifiable_type', User::class)
                            ->whereIn('notifiable_id', $users->pluck('id'))
                            ->whereJsonContains('data->chapter_id', $chapter->id)
                            ->pluck('notifiable_id');

                        $pending = $users->reject(fn ($user) => $alreadyNotified->contains($user->id));

                        if ($pending->isNotEmpty()) {
                            Notification::sendNow($pending, new NewChapterPublished($chapter));
                        }
                    });

                return true;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }
}
