<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class StorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $categories = Category::all();
        $tags = Tag::all();

        if ($users->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('Cần seed Users và CategorySeeder trước khi seed Story.');

            return;
        }

        Story::factory(30)
            ->recycle($users) // dùng lại user có sẵn thay vì tạo user mới cho mỗi story
            ->create()
            ->each(function (Story $story) use ($categories, $tags) {
                // Các cờ này không nằm trong $fillable nên gán bằng forceFill().
                $story->forceFill([
                    'status_publish' => fake()->boolean(90),
                    'is_hot' => fake()->boolean(20),
                    'is_featured' => fake()->boolean(15),
                    'is_recommended' => fake()->boolean(15),
                ])->save();

                $story->categories()->sync(
                    $categories->random(random_int(1, 3))->pluck('id')
                );

                if ($tags->isNotEmpty()) {
                    $story->tags()->sync(
                        $tags->random(min($tags->count(), random_int(2, 5)))->pluck('id')
                    );
                }
            });
    }
}
