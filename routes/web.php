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
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StoryListController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tim-kiem', [SearchController::class, 'index'])->name('search');
Route::get('/danh-sach-truyen', [StoryListController::class, 'index'])->name('stories.index'); // MỚI
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

    Route::post('/truyen/{story:slug}/yeu-thich', [FavoriteController::class, 'toggle'])
            ->middleware('throttle:20,1')
            ->name('favorites.toggle');
    Route::patch('/truyen/{story:slug}/thong-bao', [FavoriteController::class, 'toggleNotify'])
            ->middleware('throttle:20,1')
            ->name('favorites.toggle-notify');
    Route::get('/yeu-thich', [FavoriteController::class, 'index'])->name('favorites.index');

    Route::post('/truyen/{story:slug}/danh-gia', [RatingController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('ratings.store');

    Route::post('/truyen/{story:slug}/binh-luan', [CommentController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('comments.store');
    Route::delete('/binh-luan/{comment}', [CommentController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('comments.destroy');

    Route::get('/lich-su-doc', [ReadingHistoryController::class, 'index'])->name('reading-history.index');
    Route::patch('/lich-su-doc/tien-do', [ReadingHistoryController::class, 'updateProgress'])
        ->middleware('throttle:30,1')
        ->name('reading-history.update-progress');

    Route::get('/thong-bao', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/thong-bao/{id}/da-doc', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::patch('/thong-bao/danh-dau-tat-ca', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
});

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-stories-{page}.xml', [SitemapController::class, 'stories'])
    ->where('page', '[0-9]+')
    ->name('sitemap.stories');
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories'])->name('sitemap.categories');
Route::get('/sitemap-static.xml', [SitemapController::class, 'staticPages'])->name('sitemap.static');

require __DIR__.'/auth.php';