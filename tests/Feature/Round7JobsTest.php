<?php

use App\Jobs\SendNewChapterNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

// Round 7 (jobs): ngân sách tổng cho vòng lặp chờ lock của job gửi
// thông báo chương mới. Giữ lock chương bằng Cache::lock để
// notifyChapter() không bao giờ vào tới DB, nên test không cần tạo
// truyện/chương thật.

test('single chapter notification job with an expired deadline does not re-dispatch when the lock is busy', function () {
    Queue::fake();

    $chapterId = 999999;
    $lock = Cache::lock('notify-chapter-'.$chapterId, 1200);
    $this->assertTrue($lock->acquire());

    try {
        // Deadline đã qua: dù lock bận, job phải dừng hẳn thay vì
        // dispatch job mới (R7-J1).
        (new SendNewChapterNotifications($chapterId, 1, now()->timestamp - 1))->handle();

        Queue::assertNotPushed(SendNewChapterNotifications::class);
    } finally {
        $lock->release();
    }
});

test('single chapter notification job with a live deadline re-dispatches with the same deadline when the lock is busy', function () {
    Queue::fake();

    $chapterId = 999998;
    $deadlineAt = now()->timestamp + 1200;
    $lock = Cache::lock('notify-chapter-'.$chapterId, 1200);
    $this->assertTrue($lock->acquire());

    try {
        (new SendNewChapterNotifications($chapterId, 1, $deadlineAt))->handle();

        // Job dispatch lại phải giữ nguyên deadline (không reset ngân
        // sách) để tổng thời gian chờ lock của cả chuỗi vẫn bị giới hạn.
        Queue::assertPushed(SendNewChapterNotifications::class, function ($job) use ($chapterId, $deadlineAt) {
            return $job->chapterId === $chapterId && $job->deadlineAt === $deadlineAt;
        });
    } finally {
        $lock->release();
    }
});
