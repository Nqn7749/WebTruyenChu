<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});


test('the final active administrator cannot delete their own account', function () {
    $adminRole = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
    ]);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'status' => true,
    ]);

    $this->actingAs($admin)
        ->from('/profile')
        ->delete('/profile', ['password' => 'password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    expect($admin->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

test('an administrator may delete their account when another active administrator remains', function () {
    $adminRole = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
    ]);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'status' => true,
    ]);
    $remainingAdmin = User::factory()->create([
        'role_id' => $adminRole->id,
        'status' => true,
    ]);

    $this->actingAs($admin)
        ->delete('/profile', ['password' => 'password'])
        ->assertRedirect('/');

    expect($admin->fresh())->toBeNull()
        ->and($remainingAdmin->fresh())->not->toBeNull();
});
