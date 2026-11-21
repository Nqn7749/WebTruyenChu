<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('changing email without the current password fails', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'name' => $user->name,
            'email' => 'new-email@example.com',
        ]);

    $response->assertSessionHasErrors('current_password');

    $this->assertSame($user->email, $user->fresh()->email);
});

test('changing email with a wrong current password fails', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'name' => $user->name,
            'email' => 'new-email@example.com',
            'current_password' => 'wrong-password',
        ]);

    $response->assertSessionHasErrors('current_password');

    $this->assertSame($user->email, $user->fresh()->email);
});

test('changing email with the correct current password succeeds', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => 'new-email@example.com',
            'current_password' => 'Password1234',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('new-email@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('updating only the name does not require the current password', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'New Name',
            'email' => $originalEmail,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('New Name', $user->name);
    $this->assertSame($originalEmail, $user->email);
    $this->assertNotNull($user->email_verified_at);
});

test('changing password rotates the remember token', function () {
    $user = User::factory()->create();
    $oldToken = $user->remember_token;

    $this->assertNotNull($oldToken);

    $this->actingAs($user)
        ->put('/password', [
            'current_password' => 'Password1234',
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ])->assertSessionHasNoErrors();

    $newToken = $user->fresh()->remember_token;

    $this->assertNotNull($newToken);
    $this->assertNotSame($oldToken, $newToken);
});

test('changing password regenerates the session id', function () {
    $user = User::factory()->create();

    // Ghim session id bằng cookie để PUT dùng đúng session đó: test
    // client không giữ cookie giữa các request nên nếu không ghim thì
    // mỗi request đã có id mới sẵn, khẳng định sẽ rỗng.
    $this->actingAs($user)->get('/profile')->assertOk();
    $beforeId = session()->getId();

    $this->actingAs($user)
        ->withCookie(config('session.cookie'), $beforeId)
        ->put('/password', [
            'current_password' => 'Password1234',
            'password' => 'NewPassword1234',
            'password_confirmation' => 'NewPassword1234',
        ])->assertSessionHasNoErrors();

    $this->assertNotSame($beforeId, session()->getId());
});

test('correct password still logs in after the per-account bucket is exhausted', function () {
    $user = User::factory()->create(['username' => 'bucket-user']);
    $key = 'login-user:'.$user->id;

    RateLimiter::clear($key);

    try {
        foreach (range(1, 20) as $i) {
            RateLimiter::hit($key, 3600);
        }

        $this->post('/login', [
            'email' => 'bucket-user',
            'password' => 'Password1234',
        ])->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($user);
    } finally {
        RateLimiter::clear($key);
    }
});

test('wrong password after the per-account bucket is exhausted returns the throttle error', function () {
    $user = User::factory()->create(['username' => 'bucket-user-2']);
    $key = 'login-user:'.$user->id;

    RateLimiter::clear($key);

    try {
        foreach (range(1, 20) as $i) {
            RateLimiter::hit($key, 3600);
        }

        $response = $this->post('/login', [
            'email' => 'bucket-user-2',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // Lỗi phải là throttle, không phải auth.failed (sai mật khẩu).
        expect(session('errors')->getBag('default')->first('email'))
            ->not->toBe(trans('auth.failed'));
    } finally {
        RateLimiter::clear($key);
    }
});
