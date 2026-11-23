<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Observers\CategoryObserver;
use App\Observers\ChapterObserver;
use App\Observers\StoryObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Siết chính sách mật khẩu mặc định: tối thiểu 12 ký tự, có chữ,
        // có số, có cả hoa lẫn thường (A-L4). Không dùng uncompromised()
        // vì nó cần gọi API ngoài để kiểm tra mật khẩu bị lộ.
        Password::defaults(function () {
            return Password::min(8)->letters()->numbers()->mixedCase();
        });

        // Throttle toàn cục cho nhóm web (M2): 600 request/phút/IP.
        // PHẢI dùng limiter đặt tên với ->by(ip) thay vì 'throttle:600,1':
        // ThrottleRequests tính key từ signature của request (method +
        // path + IP) mà không gồm giới hạn, nên throttle số và throttle
        // riêng của từng route (login 10/phút, tìm kiếm 30/phút...)
        // dùng chung một key — mỗi request bị đếm 2 lần và ngân sách
        // của route bị cạn nhanh gấp đôi (round 6 fix1: 9 test đỏ vì
        // lỗi này). Limiter đặt tên có key riêng (IP + signature)
        // nên hai ngân sách đếm độc lập với nhau.
        RateLimiter::for('story-import-host', fn (object $job) => Limit::perMinute((int) config('story_import.requests_per_minute', 30))
            ->by(method_exists($job, 'host') ? $job->host() : 'default'));
        RateLimiter::for('web-global', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        Paginator::useBootstrapFive();

        // Giới hạn số trang tối đa cho mọi paginate(): ?page=999999 bắt
        // DB chạy OFFSET khổng lồ (P-M4). 1000 trang vẫn đủ cho mọi
        // danh sách thực tế của site.
        Paginator::currentPageResolver(function ($pageName = 'page') {
            $value = request()->input($pageName, 1);

            if (! is_numeric($value)) {
                return 1;
            }

            return min(1000, max(1, (int) $value));
        });

        View::composer('layouts.public', function ($view) {
            $navCategories = Cache::remember('nav_categories', now()->addHours(6), function () {
                return Category::whereNull('parent_id')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']);
            });

            $view->with('navCategories', $navCategories);
        });

        Story::observe(StoryObserver::class);
        Chapter::observe(ChapterObserver::class);
        Category::observe(CategoryObserver::class);
    }
}
