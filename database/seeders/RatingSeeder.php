<?php

namespace Database\Seeders;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;

class RatingSeeder extends Seeder
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
            $raters = $users->random(min($users->count(), random_int(0, 20)));

            foreach ($raters as $user) {
                $story->ratings()->updateOrCreate(
                    ['user_id' => $user->id, 'story_id' => $story->id],
                    ['score' => random_int(1, 5)]
                );
            }

            // Tính lại average_rating/rating_count bằng AVG/COUNT để tránh lệch số liệu
            $stats = $story->ratings()
                ->selectRaw('COUNT(*) as total, AVG(score) as avg_score')
                ->first();

            $story->forceFill([
                'rating_count'   => (int) $stats->total,
                'average_rating' => round((float) $stats->avg_score, 2),
            ])->save();
        });
    }
}