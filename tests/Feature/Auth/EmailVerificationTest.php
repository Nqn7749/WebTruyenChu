<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('email verification routes are enabled', function () {
    expect(Route::has('verification.notice'))->toBeTrue()
        ->and(Route::has('verification.verify'))->toBeTrue()
        ->and(Route::has('verification.send'))->toBeTrue();
});

test('unverified users can access the writer area under the current authentication-only middleware', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('writer.stories.index'))
        ->assertOk();
});
