<?php

namespace App\Notifications;

use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// Không implements ShouldQueue: thông báo luôn được gửi đồng bộ (sendNow)
// bên trong job SendNewChapterNotifications (job đó đã chạy trên queue).
class NewChapterPublished extends Notification
{
    use Queueable;

    public function __construct(
        public Chapter $chapter
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $story = $this->chapter->story;

        return [
            'story_id' => $this->chapter->story_id,
            'story_title' => $story?->title ?? '',
            'story_slug' => $story?->slug ?? '',

            'chapter_id' => $this->chapter->id,
            'chapter_number' => $this->chapter->chapter_number,
            'chapter_title' => $this->chapter->title,

            'message' => ($story?->title ?? 'Truyện')
                .' vừa có chương mới: Chương '
                .$this->chapter->chapter_number
                .($this->chapter->title
                    ? ' - '.$this->chapter->title
                    : ''),

            // Lưu URL luôn
            'url' => $story
                ? route('chapters.show', [
                    $story,
                    $this->chapter->slug,
                ])
                : url('/'),
        ];
    }
}
