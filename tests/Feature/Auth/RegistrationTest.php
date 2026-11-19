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
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertNull($user->email_verified_at);

    $response->assertRedirect(route('home', absolute: false));
});

test('users without verified email can access authenticated pages but not the writer area', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    // Khu vực sáng tác yêu cầu xác minh email: chưa xác minh thì bị chuyển
    // tới trang nhắc xác minh.
    $this->actingAs($user)
        ->get(route('writer.stories.index'))
        ->assertRedirect(route('verification.notice'));
});
