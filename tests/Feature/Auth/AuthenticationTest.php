<?php

use App\Models\User;

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

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
