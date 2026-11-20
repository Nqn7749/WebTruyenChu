<?php

use App\Models\User;

test('email verification notice uses the Bootstrap public layout', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Xác minh địa chỉ email')
        ->assertSee('btn btn-jade', false)
        ->assertSee('app-navbar', false);
});

test('password recovery and confirmation screens use the Bootstrap public layout', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Quên mật khẩu?')
        ->assertSee('form-control', false)
        ->assertSee('app-navbar', false);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertSee('Xác nhận mật khẩu')
        ->assertSee('btn btn-jade', false);
});

test('dashboard uses the public Bootstrap layout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Xin chào, '.$user->name)
        ->assertSee('app-navbar', false)
        ->assertDontSee('x-app-layout');
});
