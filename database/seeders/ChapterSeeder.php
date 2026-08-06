<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Database\Seeder;

class ChapterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Story::all()->each(function (Story $story) {
            $chapterCount = random_int(10, 40);

            $chapters = Chapter::factory()
                ->count($chapterCount)
                ->sequence(fn ($sequence) => [
                    'chapter_number' => $sequence->index + 1,
                    'title'          => 'Chương ' . ($sequence->index + 1) . ': ' . fake()->sentence(4),
                    'slug'           => 'chuong-' . ($sequence->index + 1),
                    'content'        => fake()->paragraphs(random_int(15, 30), true),
                ])
                ->for($story)
                ->create();

            // chapter_count / last_chapter_at KHÔNG nằm trong $fillable => dùng forceFill()
            $story->forceFill([
                'chapter_count'   => $chapters->count(),
                'last_chapter_at' => now(),
            ])->save();
        });
    }
}