<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\CrawlerSourceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Admin\StoryImportController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Toàn bộ khu vực admin giới hạn 60 request/phút (M1): trước đây chỉ
// vài route phá hoại có throttle:30,1, còn lại (destroy truyện/chương,
// update user...) không giới hạn. Route nào đã có throttle:30,1 thì
// vẫn giữ mức chặt hơn đó vì cả hai middleware đều chạy.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/', DashboardController::class)
        ->name('dashboard');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::patch('reports/{report}', [ReportController::class, 'update'])->middleware('throttle:30,1')->name('reports.update');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    Route::get('crawler', [CrawlerSourceController::class, 'index'])->name('crawler.index');
    Route::post('crawler', [CrawlerSourceController::class, 'store'])->middleware('throttle:10,1')->name('crawler.store');
    Route::post('crawler/{crawlerSource}/run', [CrawlerSourceController::class, 'run'])->middleware('throttle:5,1')->name('crawler.run');
    Route::delete('crawler/{crawlerSource}', [CrawlerSourceController::class, 'destroy'])->name('crawler.destroy');

    Route::get('story-import', [StoryImportController::class, 'showPreview'])->name('story-import.index');
    Route::get('story-import/batches/{batchId}', [StoryImportController::class, 'batch'])->name('story-import.batch');
    Route::post('story-import/preview', [StoryImportController::class, 'preview'])->middleware('throttle:10,1')->name('story-import.preview');
    Route::post('story-import', [StoryImportController::class, 'import'])->middleware('throttle:3,1')->name('story-import.store');

    Route::resource('categories', CategoryController::class)
        ->except(['show']);

    Route::resource('tags', TagController::class)
        ->except(['show']);

    // Admin xem danh sách, duyệt/gỡ công khai và xóa truyện; không sửa nội dung truyện.
    Route::resource('stories', StoryController::class)
        ->only(['index', 'destroy']);

    Route::delete('stories/{story}/force', [StoryController::class, 'forceDestroy'])
        ->middleware('throttle:30,1')
        ->name('stories.force-destroy');

    Route::patch('stories/{story}/toggle-publish', [StoryController::class, 'togglePublish'])
        ->middleware('throttle:30,1')
        ->name('stories.toggle-publish');

    Route::patch('stories/{story}/toggle-flag', [StoryController::class, 'toggleFlag'])
        ->middleware('throttle:30,1')
        ->name('stories.toggle-flag');

    Route::resource('stories.chapters', ChapterController::class)
        ->except(['show'])
        ->shallow();

    Route::delete('stories/{story}/chapters/{chapter}/force', [ChapterController::class, 'forceDestroy'])
        ->middleware('throttle:30,1')
        ->name('stories.chapters.force-destroy');

    Route::resource('users', UserController::class)
        ->only(['index', 'edit', 'update']);

    Route::resource('comments', CommentController::class)
        ->only(['index', 'destroy']);

    Route::patch(
        'comments/{comment}/toggle-hidden',
        [CommentController::class, 'toggleHidden']
    )->middleware('throttle:30,1')->name('comments.toggle-hidden');
});
