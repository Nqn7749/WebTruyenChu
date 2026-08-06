<?php

namespace Database\Seeders;

use App\Models\ReadingHistory;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReadingHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stories = Story::with('chapters')
            ->where('status_publish', true)
            ->has('chapters')
            ->get();

        if ($stories->isEmpty()) {
            return;
        }

        User::all()->each(function (User $user) use ($stories) {
            $storiesRead = $stories->random(min($stories->count(), random_int(0, 10)));

            foreach ($storiesRead as $story) {
                $chapter = $story->chapters->random();

                // unique(user_id, story_id) => updateOrCreate
                ReadingHistory::updateOrCreate(
                    ['user_id' => $user->id, 'story_id' => $story->id],
                    [
                        'chapter_id' => $chapter->id,
                        'read_at'    => fake()->dateTimeBetween('-1 month', 'now'),
                    ]
                );
            }
        });
    }
}