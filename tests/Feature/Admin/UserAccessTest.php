<?php

use App\Http\Controllers\Admin\UserController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

test('the final active administrator cannot be deactivated', function () {
    $lastAdmin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'status' => true,
    ]);

    // Invoke the controller directly to isolate the invariant from the
    // active-user middleware: only one active administrator remains.
    $inactiveAdminActor = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'status' => false,
    ]);

    $this->actingAs($inactiveAdminActor);

    $request = Request::create('/admin/users/'.$lastAdmin->id, 'PUT', [
        'role_id' => $this->adminRole->id,
        'status' => false,
    ]);

    expect(fn () => app(UserController::class)->update($request, $lastAdmin))
        ->toThrow(ValidationException::class);

    expect($lastAdmin->fresh()->status)->toBeTrue();
});
