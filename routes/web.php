<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReadingHistoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tim-kiem', [SearchController::class, 'index'])->name('search');
Route::get('/the-loai/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/truyen/{story:slug}', [StoryController::class, 'show'])->name('stories.show');
Route::get('/truyen/{story:slug}/chuong/{chapter:chapter_number}', [ChapterController::class, 'show'])
    ->name('chapters.show')
    ->scopeBindings();

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/truyen/{story:slug}/yeu-thich', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/yeu-thich', [FavoriteController::class, 'index'])->name('favorites.index');

    Route::post('/truyen/{story:slug}/danh-gia', [RatingController::class, 'store'])->name('ratings.store');

    Route::post('/truyen/{story:slug}/binh-luan', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/binh-luan/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('/lich-su-doc', [ReadingHistoryController::class, 'index'])->name('reading-history.index');
});

require __DIR__.'/auth.php';