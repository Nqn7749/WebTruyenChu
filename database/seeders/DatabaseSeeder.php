<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production receives only required reference data. Demo content is
     * intentionally limited to local/testing environments.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call([
            DemoUserSeeder::class,
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
