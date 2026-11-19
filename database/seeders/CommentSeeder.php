<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
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
            $chapters = $story->chapters;
            $commentTotal = random_int(0, 10);

            for ($i = 0; $i < $commentTotal; $i++) {
                $onChapter = $chapters->isNotEmpty() && fake()->boolean(70);

                // user_id/is_hidden không nằm trong $fillable của Comment
                // nên tạo xong gán riêng bằng forceFill().
                $comment = Comment::create([
                    'story_id' => $story->id,
                    'chapter_id' => $onChapter ? $chapters->random()->id : null,
                    'content' => fake()->realText(random_int(30, 150)),
                ]);
                $comment->forceFill([
                    'user_id' => $users->random()->id,
                    'is_hidden' => false,
                ])->save();

                // 40% có 1 reply
                if (fake()->boolean(40)) {
                    $reply = Comment::create([
                        'story_id' => $story->id,
                        'chapter_id' => $comment->chapter_id,
                        'parent_id' => $comment->id,
                        'content' => fake()->realText(random_int(20, 100)),
                    ]);
                    $reply->forceFill([
                        'user_id' => $users->random()->id,
                        'is_hidden' => false,
                    ])->save();
                }
            }

            $story->forceFill([
                'comment_count' => $story->comments()->count(),
            ])->save();

            foreach ($chapters as $chapter) {
                // comment_count không nằm trong $fillable của Chapter nên gán bằng forceFill().
                $chapter->forceFill([
                    'comment_count' => $chapter->comments()->count(),
                ])->saveQuietly();
            }
        });
    }
}
