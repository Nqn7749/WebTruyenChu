<?php

namespace App\Notifications;

use App\Models\Chapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewChapterPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Chapter $chapter
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $story = $this->chapter->story;

        return [
            'story_id' => $story->id,
            'story_title' => $story->title,
            'story_slug' => $story->slug,

            'chapter_id' => $this->chapter->id,
            'chapter_number' => $this->chapter->chapter_number,
            'chapter_title' => $this->chapter->title,

            'message' => $story->title
                . ' vừa có chương mới: Chương '
                . $this->chapter->chapter_number
                . ($this->chapter->title
                    ? ' - ' . $this->chapter->title
                    : ''),

            // Lưu URL luôn
            'url' => route('chapters.show', [
                $story,
                $this->chapter,
            ]),
        ];
    }
}