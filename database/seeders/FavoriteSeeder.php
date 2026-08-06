<?php

namespace Database\Seeders;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        Story::all()->each(function (Story $story) use ($users) {
            $fans = $users->random(min($users->count(), random_int(0, 15)));

            foreach ($fans as $user) {
                // firstOrCreate tránh vi phạm unique(user_id, story_id)
                $story->favorites()->firstOrCreate(['user_id' => $user->id]);
            }

            $story->forceFill([
                'favorite_count' => $story->favorites()->count(),
            ])->save();
        });
    }
}