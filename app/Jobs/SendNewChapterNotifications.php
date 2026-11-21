<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Bus\Queueable;
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
     * Số lần thử lại khi gửi lỗi giữa chừng (xem retry_after 90s ở
     * config/queue.php). Kèm dedup ở handle() để retry không gửi trùng.
     */
    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public int $chapterId
    ) {}

    public function handle(): void
    {
        self::notifyChapter($this->chapterId);
    }

    /**
     * Gửi thông báo chương mới cho người theo dõi truyện. Tách thành
     * static để job gửi hàng loạt (SendBulkChapterNotifications) dùng
     * chung mà không phải dispatch 1 job cho mỗi chương (N-L5).
     */
    public static function notifyChapter(int $chapterId): void
    {
        $chapter = Chapter::query()
            ->with('story')
            ->find($chapterId);

        if (! $chapter || ! $chapter->status || ! $chapter->story?->status_publish) {
            return;
        }

        // Khóa theo chương để 2 job cùng chương chạy song song không gửi
        // trùng: job vào sau chờ tối đa 60 giây, rồi chạy lại bước dedup
        // (lúc đó người đã nhận đã có trong bảng notifications nên bị loại).
        // Bảng cache_locks đã có sẵn trong migration tạo bảng cache.
        Cache::lock('notify-chapter-'.$chapterId, 120)->block(60, function () use ($chapter) {
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
        });
    }
}
