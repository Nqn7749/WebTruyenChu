<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(RoleSeeder::class);

        $adminRoleId = Role::where('slug', 'admin')->value('id');

        User::factory()->create([
            'role_id' => $adminRoleId,
            'username' => 'admin',
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $this->call([
            CategorySeeder::class,
            TagSeeder::class,
            // StorySeeder::class,   // khi đã có logic
            // ChapterSeeder::class, // khi đã có logic
        ]);
        }
}
