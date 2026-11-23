<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

test('every admin page links back to the public home page', function () {
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $role->id, 'status' => true, 'email_verified_at' => now()]);

    foreach (['admin.dashboard', 'admin.stories.index', 'admin.story-import.index', 'admin.categories.index'] as $name) {
        $html = $this->actingAs($admin)->get(route($name))->assertOk()->getContent();

        expect($html)
            ->toContain('Về trang chủ')
            ->toContain('Xem website')
            ->and(substr_count($html, 'href="'.route('home').'"'))->toBeGreaterThanOrEqual(2);
    }
});
