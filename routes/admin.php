<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)
    ->name('dashboard');

Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::patch('reports/{report}', [ReportController::class, 'update'])->middleware('throttle:30,1')->name('reports.update');
Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

Route::resource('categories', CategoryController::class)
    ->except(['show']);

Route::resource('tags', TagController::class)
    ->except(['show']);

// Admin xem danh sách, duyệt/gỡ công khai và xóa truyện; không sửa nội dung truyện.
Route::resource('stories', StoryController::class)
    ->only(['index', 'destroy']);

Route::patch('stories/{story}/toggle-publish', [StoryController::class, 'togglePublish'])
    ->middleware('throttle:30,1')
    ->name('stories.toggle-publish');

Route::patch('stories/{story}/toggle-flag', [StoryController::class, 'toggleFlag'])
    ->middleware('throttle:30,1')
    ->name('stories.toggle-flag');

Route::resource('stories.chapters', ChapterController::class)
    ->except(['show'])
    ->shallow();

Route::resource('users', UserController::class)
    ->only(['index', 'edit', 'update']);

Route::resource('comments', CommentController::class)
    ->only(['index', 'destroy']);

Route::patch(
    'comments/{comment}/toggle-hidden',
    [CommentController::class, 'toggleHidden']
)->middleware('throttle:30,1')->name('comments.toggle-hidden');
