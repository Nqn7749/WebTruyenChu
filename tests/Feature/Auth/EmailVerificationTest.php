<?php

use App\\Models\\User;
use Illuminate\\Support\\Facades\\Route;

test('email verification routes are disabled temporarily', function () {
    expect(Route::has('verification.notice'))->toBeFalse()
        ->and(Route::has('verification.verify'))->toBeFalse()
        ->and(Route::has('verification.send'))->toBeFalse();
});

test('users with unverified email can access dashboard and writer area', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    $this->actingAs($user)
        ->get(route('writer.stories.index'))
        ->assertOk();
});
