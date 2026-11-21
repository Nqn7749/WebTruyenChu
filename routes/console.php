<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stories:delete-without-chapters')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::call(function () {
    // Thông báo đã đọc quá 90 ngày thì xóa: bảng notifications không
    // giới hạn sẽ phình vô hạn (P-M2). Thông báo chưa đọc không xóa
    // để người dùng vẫn xem được.
    DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();
})->dailyAt('03:00')->name('notifications:prune-read');
