<?php

namespace Database\Factories;

use App\Models\Story;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'author_name' => fake()->name(),
            'description' => fake()->paragraphs(3, true),
            'status' => fake()->randomElement([
                'ongoing',
                'completed',
                'paused'
            ]),
            'views' => fake()->numberBetween(0, 50000),
        ];
    }
}
