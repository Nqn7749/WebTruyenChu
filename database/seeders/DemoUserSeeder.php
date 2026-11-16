<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Create disposable user accounts for local development and tests.
     */
    public function run(): void
    {
        $userRoleId = Role::where('slug', 'user')->value('id');

        if (! $userRoleId) {
            $this->command?->warn('Role user chưa được khởi tạo.');

            return;
        }

        User::factory(20)->create([
            'role_id' => $userRoleId,
        ]);
    }
}
