<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;
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
            Route::middleware(['web', 'auth', 'verified', 'role:admin']) //            Route::middleware(['web', 'auth', 'verified', 'role:admin'])

                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // KHÔNG tin proxy nào theo mặc định: site chưa deploy, chưa biết
        // production có load balancer/proxy hay không. trustProxies('*')
        // tin X-Forwarded-For từ bất cứ ai nên ai cũng giả được IP, qua
        // mặt throttle login/tìm kiếm và thổi view (NL-9). Khi deploy,
        // nếu sau load balancer/proxy thì thêm lại với đúng IP của nó,
        // vd trustProxies(at: ['123.45.67.89']); nếu chạy trực tiếp
        // (Nginx -> PHP-FPM, không proxy) thì để nguyên như này.
        // Lưu ý: nếu deploy sau proxy mà quên thêm lại, request()->ip()
        // sẽ ra IP của proxy cho mọi user nên throttle login/view đếm
        // chung — test đăng nhập sẽ thấy 429 ngay, kiểm tra lúc deploy.

        //
        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->web(append: [
            // Chặn hammer toàn cục: 600 request/phút/IP cho mọi route web
            // (M2) — đủ rộng để người dùng thường không bao giờ chạm, chỉ
            // chặn bot quét. Dùng limiter đặt tên 'web-global' (định nghĩa
            // ở AppServiceProvider) để key của nó không trùng với throttle
            // riêng của từng route (xem ghi chú ở đó).
            'throttle:web-global',
            EnsureUserIsActive::class,
            // Vô hiệu session khác khi đổi mật khẩu: logoutOtherDevices
            // chỉ hash lại mật khẩu, chính middleware này mới so hash
            // trong session với hash hiện tại và đăng xuất các session
            // có hash cũ ở request tiếp theo (xem PasswordController).
            AuthenticateSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
