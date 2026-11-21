<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Khu vực admin yêu cầu xác minh email giống khu vực sáng tác
            // (NL-2): admin đổi email sẽ bị vô hiệu xác minh ở
            // ProfileController, không nên giữ toàn quyền /admin với email
            // chưa xác minh. Lưu ý: admin tạo qua app:create-admin đã có
            // email_verified_at nên không bị khóa ngoài.
            Route::middleware(['web', 'auth', 'verified', 'role:admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // App chạy sau load balancer (xem docs/production-readiness.md):
        // tin X-Forwarded-For để request()->ip() ra IP thật của visitor
        // (đếm view và throttle login đều dựa vào IP).
        $middleware->trustProxies(at: '*');

        //
        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
