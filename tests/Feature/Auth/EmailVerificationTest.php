<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('email verification routes are enabled', function () {
    expect(Route::has('verification.notice'))->toBeTrue()
        ->and(Route::has('verification.verify'))->toBeTrue()
        ->and(Route::has('verification.send'))->toBeTrue();
});

test('unverified users are redirected to the email verification notice when opening the writer area', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('writer.stories.index'))
        ->assertRedirect(route('verification.notice'));
});
