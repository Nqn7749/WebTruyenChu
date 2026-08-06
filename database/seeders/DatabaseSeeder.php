<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
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
        $this->call(RoleSeeder::class);

        $adminRoleId = Role::where('slug', 'admin')->value('id');
        $userRoleId  = Role::where('slug', 'user')->value('id');

        User::factory()->create([
            'role_id'  => $adminRoleId,
            'username' => 'admin',
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
        ]);

        // 20 user thường để test rating / favorite / comment / reading history
        User::factory(20)->create([
            'role_id' => $userRoleId,
        ]);

        $this->call([
            CategorySeeder::class,
            TagSeeder::class,
            StorySeeder::class,
            ChapterSeeder::class,
            RatingSeeder::class,
            FavoriteSeeder::class,
            CommentSeeder::class,
            ReadingHistorySeeder::class,
        ]);
    }
}