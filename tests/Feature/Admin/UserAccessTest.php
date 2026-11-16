<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->adminRole = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
    ]);

    $this->userRole = Role::create([
        'name' => 'User',
        'slug' => 'user',
    ]);
});

test('an administrator cannot remove their own administrator access', function () {
    $admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'status' => true,
    ]);

    $response = $this->actingAs($admin)->put(
        route('admin.users.update', $admin),
        [
            'role_id' => $this->userRole->id,
            'status' => true,
        ]
    );

    $response->assertSessionHasErrors('role_id');

    expect($admin->fresh()->role_id)->toBe($this->adminRole->id);
});

test('the final active administrator cannot be demoted', function () {
    $admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'status' => true,
    ]);

    $otherUser = User::factory()->create([
        'role_id' => $this->userRole->id,
        'status' => true,
    ]);

    $response = $this->actingAs($admin)->put(
        route('admin.users.update', $otherUser),
        [
            'role_id' => $this->userRole->id,
            'status' => false,
        ]
    );

    $response->assertSessionHasNoErrors();
    expect($otherUser->fresh()->status)->toBeFalse();
});

test('the final active administrator cannot be deactivated by another administrator', function () {
    $lastAdmin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'status' => true,
    ]);

    $actingAdmin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'status' => true,
    ]);

    // Keep one additional active administrator, then verify the remaining
    // administrator cannot be removed after the first is deactivated.
    $this->actingAs($actingAdmin)->put(
        route('admin.users.update', $lastAdmin),
        [
            'role_id' => $this->adminRole->id,
            'status' => false,
        ]
    )->assertSessionHasNoErrors();

    $response = $this->actingAs($lastAdmin)->put(
        route('admin.users.update', $actingAdmin),
        [
            'role_id' => $this->adminRole->id,
            'status' => false,
        ]
    );

    $response->assertSessionHasErrors('role_id');
});
