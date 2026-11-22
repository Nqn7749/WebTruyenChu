<?php

use App\Models\User;

test('registration screen can be rendered with the redesigned form', function () {
    $response = $this->get('/register');

    $response->assertOk()
        ->assertSee('Tạo tài khoản')
        ->assertSee('Tên đăng nhập')
        ->assertSee('auth.css');
});

test('new users can register without email verification', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'test@example.com',
        'password' => 'Password1234',
        'password_confirmation' => 'Password1234',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertNull($user->email_verified_at);

    $response->assertRedirect(route('home', absolute: false));
});

test('successful registration regenerates the session identifier', function () {
    $this->withSession(['pre_register_marker' => 'present']);

    $originalSessionId = session()->getId();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'test@example.com',
        'password' => 'Password1234',
        'password_confirmation' => 'Password1234',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    $response->assertRedirect(route('home', absolute: false));
    expect(session()->getId())->not->toBe($originalSessionId);
    expect(session()->get('pre_register_marker'))->toBe('present');
    $this->assertAuthenticatedAs($user);
});

test('users without verified email can access authenticated pages and the writer area', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    // routes/web.php currently protects the writer area with auth only.
    // Keep this assertion aligned with that existing middleware policy.
    $this->actingAs($user)
        ->get(route('writer.stories.index'))
        ->assertOk();
});
