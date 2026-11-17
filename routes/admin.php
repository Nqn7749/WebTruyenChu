<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)
    ->name('dashboard');

Route::resource('categories', CategoryController::class)
    ->except(['show']);

Route::resource('tags', TagController::class)
    ->except(['show']);

// Admin chỉ được xem danh sách và xóa truyện; không có thao tác duyệt/sửa truyện.
Route::resource('stories', StoryController::class)
    ->only(['index', 'destroy']);

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
)->name('comments.toggle-hidden');
