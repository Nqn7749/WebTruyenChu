<?php

use App\Models\User;

test('active users can continue accessing authenticated pages', function () {
    $user = User::factory()->create(['status' => true]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk();
});

test('users disabled during an authenticated session are logged out and their session is invalidated', function () {
    $user = User::factory()->create(['status' => true]);

    $this->actingAs($user)->withSession([
        'pre_disable_marker' => 'present',
        '_token' => 'old-csrf-token',
    ]);

    $user->forceFill(['status' => false])->save();

    $this->get('/profile')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(session()->has('pre_disable_marker'))->toBeFalse();
    expect(session()->token())->not->toBe('old-csrf-token');
});
