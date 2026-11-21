<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RunCrawlerSource;
use App\Models\CrawlerRun;
use App\Models\CrawlerSource;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('stories:delete-without-chapters')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::call(function () {
    // Thông báo đã đọc quá 90 ngày thì xóa: bảng notifications không
    // giới hạn sẽ phình vô hạn (P-M2).
    DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();

    // Thông báo chưa đọc quá 365 ngày cũng xóa: giữ lại 1 năm để người
    // dùng còn xem lại được, quá hạn đó thì coi như không còn giá trị
    // (C5). Đây là quyết định sản phẩm: tài khoản không đăng nhập trong
    // 1 năm sẽ mất các thông báo cũ chưa đọc.
    DB::table('notifications')
        ->whereNull('read_at')
        ->where('created_at', '<', now()->subDays(365))
        ->delete();
})->dailyAt('03:00')->name('notifications:prune');

Schedule::call(function () {
    CrawlerSource::query()
        ->where('enabled', true)
        ->where('permission_confirmed', true)
        ->chunkById(50, function ($sources) {
            foreach ($sources as $source) {
                if ($source->runs()->whereIn('status', ['queued', 'running'])->exists()) {
                    continue;
                }

                $run = CrawlerRun::create([
                    'crawler_source_id' => $source->id,
                    'status' => 'queued',
                ]);

                RunCrawlerSource::dispatch($run->id);
            }
        });
})->everySixHours()->name('crawler:authorized-sources')->withoutOverlapping();
