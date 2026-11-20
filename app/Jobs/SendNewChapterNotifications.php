<?php

namespace App\Jobs;

use App\Models\Chapter;
use App\Models\User;
use App\Notifications\NewChapterPublished;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification as NotificationFacade;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendNewChapterNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
                Notification::sendNow($users, new NewChapterPublished($chapter));
            });
    }
}
