<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered with the redesigned form', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('Đăng nhập')
        ->assertSee('Email hoặc tên đăng nhập')
        ->assertSee('auth.css');
});

test('users can authenticate using the login screen and return to home by default', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('users are redirected to their intended page after login', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['url.intended' => route('profile.edit')])
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('profile.edit'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can authenticate with their username', function () {
    $user = User::factory()->create(['username' => 'reader-login']);

    $response = $this->post('/login', [
        'email' => 'reader-login',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('home', absolute: false));
});

test('successful login clears previous failed attempts for the same identity and IP', function () {
    $user = User::factory()->create(['username' => 'reader-login']);
    $key = 'reader-login|127.0.0.1';

    RateLimiter::clear($key);
    RateLimiter::hit($key);
    RateLimiter::hit($key);

    try {
        $this->post('/login', [
            'email' => 'reader-login',
            'password' => 'password',
        ])->assertRedirect(route('home', absolute: false));

        expect(RateLimiter::tooManyAttempts($key, 5))->toBeFalse();
    } finally {
        RateLimiter::clear($key);
    }
});

test('disabled accounts cannot authenticate even with the correct password', function () {
    $user = User::factory()->create(['status' => false]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('login throttling locks only the same normalized identity and IP after five failures', function () {
    $key = 'throttle-user|127.0.0.1';
    $otherIdentityKey = 'another-user|127.0.0.1';

    RateLimiter::clear($key);
    RateLimiter::clear($otherIdentityKey);

    try {
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'email' => $attempt === 1 ? 'THROTTLE-USER' : 'throttle-user',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();

        $this->post('/login', [
            'email' => 'throttle-user',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->post('/login', [
            'email' => 'another-user',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        expect(RateLimiter::tooManyAttempts($otherIdentityKey, 5))->toBeFalse();
    } finally {
        RateLimiter::clear($key);
        RateLimiter::clear($otherIdentityKey);
    }
});

test('login throttling shares one budget between email and username for the same account', function () {
    $user = User::factory()->create([
        'username' => 'shared-budget',
        'email' => 'shared-budget@example.com',
    ]);
    $key = 'user:'.$user->id.'|127.0.0.1';

    RateLimiter::clear($key);

    try {
        foreach (range(1, 3) as $attempt) {
            $this->post('/login', [
                'email' => 'shared-budget@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        foreach (range(1, 2) as $attempt) {
            $this->post('/login', [
                'email' => 'shared-budget',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();

        $this->post('/login', [
            'email' => 'shared-budget@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    } finally {
        RateLimiter::clear($key);
    }
});

test('login route throttle blocks password spraying across many accounts', function () {
    foreach (range(1, 10) as $attempt) {
        $this->post('/login', [
            'email' => 'spray-'.$attempt.'@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/login', [
        'email' => 'spray-11@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('successful login regenerates the session identifier', function () {
    $user = User::factory()->create();
    $this->withSession(['pre_login_marker' => 'present']);

    $originalSessionId = session()->getId();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('home', absolute: false));

    expect(session()->getId())->not->toBe($originalSessionId);
    $this->assertAuthenticatedAs($user);
});

test('logout invalidates the previous session and regenerates the csrf token', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession([
        'pre_logout_marker' => 'present',
        '_token' => 'old-csrf-token',
    ]);

    $this->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
    expect(session()->has('pre_logout_marker'))->toBeFalse();
    expect(session()->token())->not->toBe('old-csrf-token');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
