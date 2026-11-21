<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

test('changing password logs out other sessions but keeps the current one', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();

    // Gửi session id bằng withCookie: test client tự mã hóa kèm
    // CookieValuePrefix nên app giải mã được. Tự mã hóa rồi gửi bằng
    // withUnencryptedCookie sẽ thiếu prefix, EncryptCookies bỏ cookie
    // và request rơi về session cũ còn trong store — B không bao giờ
    // dùng đúng session row của mình (round 6 fix2 sai ở đây).
    // Kèm forgetGuards trước mỗi lần đổi thiết bị để guard đọc lại
    // user từ đúng session row của thiết bị đó.
    $sessionCookie = config('session.cookie');

    // Thiết bị A đăng nhập trong test client.
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'Password1234',
    ])->assertRedirect(route('home', absolute: false));
    $deviceAId = session()->getId();

    // Thiết bị B: giả lập một thiết bị khác đang đăng nhập bằng cách
    // chèn thẳng một session row đúng dạng session thật sau khi login:
    // key auth của guard + password_hash_web là HMAC của hash mật khẩu
    // lúc đăng nhập — đúng key mà AuthenticateSession đọc
    // (xem SessionGuard::login).
    $deviceBId = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $deviceBId,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode(serialize([
            '_token' => Str::random(40),
            'password_hash_web' => auth('web')->hashPasswordForCookie($user->getAuthPassword()),
            auth('web')->getName() => $user->id,
        ])),
        'last_activity' => now()->timestamp,
    ]);

    // Thiết bị B đang dùng được trước khi đổi mật khẩu.
    auth()->forgetGuards();
    $this->withCookie($sessionCookie, $deviceBId)
        ->get('/profile')->assertOk();

    // Thiết bị A đổi mật khẩu.
    auth()->forgetGuards();
    $this->withCookie($sessionCookie, $deviceAId)
        ->put('/password', [
            'current_password' => 'Password1234',
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ])->assertSessionHasNoErrors();

    // Mật khẩu phải thực sự đổi (khẳng định này để test không rỗng
    // nếu PUT bị redirect mà không báo lỗi ở bag mặc định).
    $this->assertTrue(Hash::check('NewPassword1234', $user->fresh()->password));

    // Đổi mật khẩu tạo lại session id (round 7): session id cũ không
    // còn dùng được (row cũ giữ hash cũ), lấy id mới cho các request
    // sau của thiết bị A.
    $deviceAId = session()->getId();

    $this->assertAuthenticatedAs($user);

    // Thiết bị A vẫn dùng được.
    auth()->forgetGuards();
    $this->withCookie($sessionCookie, $deviceAId)
        ->get('/profile')->assertOk();

    // Thiết bị B bị đăng xuất: hash mật khẩu trong session cũ không
    // còn khớp sau khi đổi mật khẩu (AuthenticateSession).
    auth()->forgetGuards();
    $this->withCookie($sessionCookie, $deviceBId)
        ->get('/profile')->assertRedirect(route('login'));
});

test('resetting password deletes all user sessions', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();

    // Giả lập 2 thiết bị đang đăng nhập của user.
    $sessionIds = [(string) Str::uuid(), (string) Str::uuid()];

    foreach ($sessionIds as $id) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize(['login_web' => $user->id])),
            'last_activity' => now()->timestamp,
        ]);
    }

    Notification::fake();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('login'));

        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

        return true;
    });
});

test('global per-account login bucket throttles wrong passwords after twenty failures regardless of IP', function () {
    $user = User::factory()->create(['username' => 'global-bucket']);
    $key = 'login-user:'.$user->id;

    RateLimiter::clear($key);

    try {
        // Trong test mọi request đều từ 127.0.0.1 nên bucket theo IP
        // sẽ khóa trước; vì vậy hit trực tiếp bucket toàn cục để
        // chứng minh nó hoạt động độc lập với IP.
        foreach (range(1, 20) as $i) {
            RateLimiter::hit($key, 3600);
        }

        // Round 7: bucket toàn cục chỉ chặn lần thử SAI — mật khẩu
        // đúng vẫn đăng nhập được (xem Round7AuthTest).
        $response = $this->post('/login', [
            'email' => 'global-bucket',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        expect(session('errors')->getBag('default')->first('email'))
            ->not->toBe(trans('auth.failed'));
    } finally {
        RateLimiter::clear($key);
    }
});

test('registration creates the user role when it does not exist yet', function () {
    Role::query()->where('slug', 'user')->delete();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'test@example.com',
        'password' => 'Password1234',
        'password_confirmation' => 'Password1234',
    ]);

    $response->assertRedirect(route('home', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->role_id)->not->toBeNull();
    expect($user->role->slug)->toBe('user');
    $this->assertAuthenticatedAs($user);
});

test('locked account login with the correct password returns the generic failure message', function () {
    $user = User::factory()->create(['status' => false]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'Password1234',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');

    // Cùng thông báo như khi sai mật khẩu, không lộ là mật khẩu đã đúng.
    expect(session('errors')->getBag('default')->first('email'))->toBe(trans('auth.failed'));
});

test('forgot password with an unknown email returns the same generic success message', function () {
    $response = $this->post('/forgot-password', ['email' => 'khong-ton-tai@example.com']);

    $response->assertSessionHasNoErrors();
    expect(session('status'))->toBe('Nếu email đã đăng ký, liên kết đặt lại mật khẩu đã được gửi.');
});

test('forgot password with a registered email sends the link and returns the same message', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', ['email' => $user->email]);

    $response->assertSessionHasNoErrors();
    expect(session('status'))->toBe('Nếu email đã đăng ký, liên kết đặt lại mật khẩu đã được gửi.');
    Notification::assertSentTo($user, ResetPassword::class);
});
