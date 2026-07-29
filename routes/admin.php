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

Route::resource('categories', CategoryController::class);

Route::resource('tags', TagController::class);

Route::resource('stories', StoryController::class);

Route::resource('stories.chapters', ChapterController::class)
    ->shallow();

Route::resource('users', UserController::class)
    ->only([
        'index',
        'edit',
        'update',
    ]);

Route::resource('comments', CommentController::class)
    ->only([
        'index',
        'destroy',
    ]);

Route::patch(
    'comments/{comment}/toggle-hidden',
    [CommentController::class, 'toggleHidden']
)->name('comments.toggle-hidden');