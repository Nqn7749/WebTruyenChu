<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Services\HomeCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes what AdSeeder and DemoActivitySeeder created, and only that: stories titled "[Demo] ...",
 *
 * their chapters, comments, favorites and statistics, the demo-*@example.test accounts, "[Demo]" ads
 * and their banners, and tags that no story uses any more.
 *
 *   php artisan db:seed --class=DemoDataCleanupSeeder
 */
class DemoDataCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $stories = Story::withTrashed()->where('title', 'like', DemoActivitySeeder::PREFIX.'%')->get();
        $storyIds = $stories->pluck('id')->all();

        if ($storyIds !== []) {
            Comment::withTrashed()->whereIn('story_id', $storyIds)->forceDelete();
            DB::table('story_daily_stats')->whereIn('story_id', $storyIds)->delete();
            DB::table('favorites')->whereIn('story_id', $storyIds)->delete();
            DB::table('story_tags')->whereIn('story_id', $storyIds)->delete();
            DB::table('story_categories')->whereIn('story_id', $storyIds)->delete();
            Chapter::withTrashed()->whereIn('story_id', $storyIds)->forceDelete();
            $stories->each(function (Story $story) {
                if ($story->cover_image) {
                    Storage::disk('public')->delete($story->cover_image);
                }
                $story->forceDelete();
            });
        }

        $users = User::query()->where('email', 'like', 'demo-%'.DemoActivitySeeder::EMAIL_DOMAIN)->get();
        foreach ($users as $user) {
            DB::table('favorites')->where('user_id', $user->id)->delete();
            Comment::withTrashed()->where('user_id', $user->id)->forceDelete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        }

        $ads = Ad::query()->where('name', 'like', AdSeeder::PREFIX.'%')->get();
        foreach ($ads as $ad) {
            if ($ad->image_path) {
                Storage::disk('public')->delete($ad->image_path);
            }
            $ad->delete();
        }

        $tags = Tag::query()->doesntHave('stories')->get();
        $tags->each->delete();

        app(HomeCacheService::class)->invalidate();
        Ad::flushCache();

        $this->command?->info('Removed '.count($storyIds).' demo stories, '.$users->count().' demo accounts, '.$ads->count().' demo ads, '.$tags->count().' unused tags.');
    }
}
