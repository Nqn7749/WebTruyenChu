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
        $chapter = Chapter::query()
            ->with('story')
            ->find($this->chapterId);

        if (! $chapter || ! $chapter->status || ! $chapter->story?->status_publish) {
            return;
        }

        User::query()
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
    }
}
