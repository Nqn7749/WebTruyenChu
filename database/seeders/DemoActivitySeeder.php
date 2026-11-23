<?php

namespace Database\Seeders;

use App\Http\Controllers\RankingController;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Favorite;
use App\Models\Role;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Services\HomeCacheService;
use App\Services\HotStoriesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Local sample data to try the home sections, rankings, automatic Hot, related stories, author/poster
 * search, shared comments and the dashboard. Everything it creates carries a "[Demo]" title or a
 *
 * demo-*@example.test email so DemoDataCleanupSeeder can remove exactly that and nothing else.
 * Running it twice does not duplicate anything.
 */
class DemoActivitySeeder extends Seeder
{
    public const PREFIX = '[Demo] ';

    public const EMAIL_DOMAIN = '@example.test';

    /** title, pen name, genre slugs, tags, status, base daily reads, base daily views, rating, ratings, favorites */
    private const STORIES = [
        ['Kiếm Đạo Độc Tôn', 'Lăng Phong', ['kiem-hiep', 'huyen-huyen'], ['kiếm hiệp', 'hệ thống'], 'ongoing', 90, 400, 4.7, 120, 40],
        ['Trọng Sinh Làm Phú Nhị Đại', 'Lăng Phong', ['trong-sinh', 'do-thi'], ['trọng sinh', 'hệ thống'], 'ongoing', 70, 300, 4.5, 95, 33],
        ['Hệ Thống Thần Y', 'Minh Nguyệt', ['do-thi', 'he-thong'], ['hệ thống', 'y thuật'], 'ongoing', 55, 280, 4.3, 60, 21],
        ['Gió Qua Mùa Hạ', 'Thanh Vân', ['ngon-tinh', 'hoc-duong'], ['ngôn tình', 'học đường'], 'completed', 40, 250, 4.9, 210, 88],
        ['Xuyên Không Thành Công Chúa', 'Thanh Vân', ['xuyen-khong', 'ngon-tinh', 'cung-dau'], ['xuyên không', 'cung đấu'], 'completed', 35, 210, 4.6, 140, 52],
        ['Mạt Thế Sinh Tồn', 'Hạ Lan', ['mat-the', 'khoa-huyen'], ['mạt thế', 'sinh tồn'], 'ongoing', 30, 190, 4.1, 48, 17],
        ['Thám Tử Phố Cổ', 'Hạ Lan', ['trinh-tham'], ['trinh thám', 'phá án'], 'ongoing', 22, 150, 4.4, 36, 12],
        ['Đế Quốc Hoàng Hôn', 'Quốc Bảo', ['lich-su', 'quan-su'], ['lịch sử', 'quân sự'], 'completed', 18, 120, 4.2, 30, 9],
        ['Võng Du Chi Vương', 'Quốc Bảo', ['vong-du', 'he-thong'], ['võng du', 'hệ thống'], 'ongoing', 25, 170, 3.9, 22, 8],
        ['Tình Yêu Cuối Thu', 'Minh Nguyệt', ['ngon-tinh-hien-dai'], ['ngôn tình', 'hiện đại'], 'ongoing', 12, 90, 4.0, 15, 5],
        ['Linh Dị Nơi Xóm Vắng', 'Dạ Vũ', ['linh-di', 'kinh-di'], ['linh dị', 'kinh dị'], 'paused', 8, 60, 3.8, 9, 3],
        ['Tiên Lộ Mờ Mịt', 'Dạ Vũ', ['tien-hiep', 'tien-hiep-he-thong'], ['tiên hiệp', 'hệ thống'], 'ongoing', 45, 260, 4.5, 80, 29],
        ['Đam Mỹ Học Viện', 'Thanh Vân', ['dam-my', 'hoc-duong'], ['đam mỹ', 'học đường'], 'ongoing', 5, 40, 3.5, 6, 2],
        ['Cuộc Sống Nơi Điền Viên', 'Hạ Lan', ['dien-van', 'hai-huoc'], ['điền văn', 'hài hước'], 'completed', 3, 25, 4.8, 7, 4],
    ];

    private const PARAGRAPHS = [
        'Trời vừa hửng sáng, sương còn đọng trên những tán lá ngoài hiên.',
        'Anh đứng lặng một lúc, nghe tiếng gió lùa qua khe cửa như một lời thì thầm quen thuộc.',
        'Không ai nói với ai câu nào, nhưng cả hai đều hiểu chuyện gì sắp xảy ra.',
        '"Đi thôi," người kia khẽ nói, ánh mắt không rời con đường phía xa.',
        'Phía sau lưng họ, thành phố vẫn thức dậy như mọi ngày, chẳng hay biết gì.',
        'Mọi chuyện bắt đầu từ một lá thư không có người gửi, và chưa ai biết nó sẽ kết thúc ở đâu.',
    ];

    public function run(): void
    {
        $roleId = Role::where('slug', 'user')->value('id');
        if (! $roleId) {
            $this->command?->warn('Role "user" is missing: run RoleSeeder first.');

            return;
        }

        $authors = $this->authors($roleId);
        $readers = $this->readers($roleId);
        $tagIds = [];

        foreach (self::STORIES as $index => $row) {
            [$title, $pen, $genres, $tags, $status, $reads, $views, $rating, $ratings, $favorites] = $row;

            $story = Story::query()->where('title', self::PREFIX.$title)->first() ?? new Story;
            $isNew = ! $story->exists;
            $poster = $authors[$pen] ?? $authors[array_key_first($authors)];

            $story->forceFill([
                'user_id' => $poster->id,
                'title' => self::PREFIX.$title,
                'slug' => $story->slug ?: (Str::slug($title).'-demo-'.Str::lower(Str::random(5))),
                'author_name' => $pen,
                'description' => 'Truyện mẫu để thử giao diện: '.$title.'. Một câu chuyện nhiều bất ngờ, đọc là cuốn.',
                'status' => $status,
                'status_publish' => true,
                'is_recommended' => $index % 4 === 0,
                'is_featured' => $index % 5 === 0,
                // Lifetime counters (the all-time rankings use these).
                'views' => $views * 40 + $index * 13,
                'reads_count' => $reads * 40 + $index * 7,
                'rating_count' => $ratings,
                'average_rating' => $rating,
            ])->save();

            $story->categories()->sync(Category::query()->whereIn('slug', $genres)->pluck('id')->all());
            foreach ($tags as $name) {
                $tagIds[$name] ??= Tag::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
            }
            $story->tags()->sync(array_map(fn ($name) => $tagIds[$name], $tags));

            if ($isNew || $story->chapters()->count() === 0) {
                $this->chapters($story, 4 + $index % 3);
            }

            $this->dailyStats($story, $reads, $views, $index);
            $this->favorites($story, $readers, min($favorites, count($readers)));

            if ($index < 4) {
                $this->comments($story, $readers);
            }
        }

        // Today the automatic ranking decides Hot from the daily statistics created above.
        app(HotStoriesService::class)->refresh();
        app(HomeCacheService::class)->invalidate();
        foreach (array_keys(RankingController::TYPES) as $type) {
            foreach (array_keys(RankingController::PERIODS) as $period) {
                Cache::forget("ranking:{$type}:{$period}");
            }
        }

        $this->command?->info('Demo data ready: '.Story::query()->where('title', 'like', self::PREFIX.'%')->count().' stories, '
            .count($authors).' authors, '.count($readers).' readers.');
        $this->command?->line('Demo accounts use the password "password" ('.self::EMAIL_DOMAIN.').');
    }

    /**
     * @return array<string, User> pen name => posting account
     */
    private function authors(int $roleId): array
    {
        $accounts = [];
        foreach (['Lăng Phong', 'Minh Nguyệt', 'Thanh Vân', 'Hạ Lan', 'Quốc Bảo', 'Dạ Vũ'] as $pen) {
            $accounts[$pen] = User::unguarded(fn () => User::query()->firstOrCreate(
                ['email' => 'demo-'.Str::slug($pen).self::EMAIL_DOMAIN],
                ['username' => 'demo_'.Str::slug($pen, '_'), 'name' => 'Demo '.$pen, 'password' => Hash::make('password'), 'role_id' => $roleId, 'status' => true, 'email_verified_at' => now()],
            ));
        }

        return $accounts;
    }

    /**
     * @return list<User>
     */
    private function readers(int $roleId): array
    {
        $readers = [];
        foreach (range(1, 12) as $i) {
            $readers[] = User::unguarded(fn () => User::query()->firstOrCreate(
                ['email' => 'demo-reader-'.$i.self::EMAIL_DOMAIN],
                ['username' => 'demo_reader_'.$i, 'name' => 'Độc giả '.$i, 'password' => Hash::make('password'), 'role_id' => $roleId, 'status' => true, 'email_verified_at' => now()],
            ));
        }

        return $readers;
    }

    private function chapters(Story $story, int $count): void
    {
        foreach (range(1, $count) as $number) {
            $paragraphs = collect(range(1, 8))->map(fn (int $i) => '<p>'.self::PARAGRAPHS[($number + $i) % count(self::PARAGRAPHS)].'</p>')->implode("\n");

            $chapter = new Chapter;
            $chapter->forceFill([
                'story_id' => $story->id,
                'chapter_number' => $number,
                'title' => 'Chương '.$number.': '.['Khởi đầu', 'Gặp gỡ', 'Bí mật', 'Thử thách', 'Lựa chọn', 'Kết thúc một chặng'][($number - 1) % 6],
                'slug' => 'chuong-'.$number.'-'.Str::lower(Str::random(6)),
                'content' => $paragraphs,
                'status' => true,
            ]);
            $chapter->save();
        }
    }

    /**
     * Ten days of activity: busy stories get a rising trend so the 7-day and 30-day rankings differ.
     */
    private function dailyStats(Story $story, int $reads, int $views, int $seed): void
    {
        DB::table('story_daily_stats')->where('story_id', $story->id)->delete();

        $rows = [];
        foreach (range(0, 9) as $daysAgo) {
            // Most of the weight in the last days for the first stories, in the older days for the later ones.
            $trend = $seed % 3 === 0 ? 1 + (9 - $daysAgo) * 0.12 : 1 + $daysAgo * 0.1;
            $noise = 0.85 + (($story->id * 7 + $daysAgo * 13) % 30) / 100;

            $rows[] = [
                'story_id' => $story->id,
                'stat_date' => today()->subDays($daysAgo)->toDateString(),
                'views' => (int) round($views * $trend * $noise / 10),
                'reads' => (int) round($reads * $trend * $noise / 10),
            ];
        }

        DB::table('story_daily_stats')->insert($rows);
    }

    /**
     * @param  list<User>  $readers
     */
    private function favorites(Story $story, array $readers, int $count): void
    {
        foreach (array_slice($readers, 0, $count) as $i => $reader) {
            // Spread them over the last 40 days so the 7-day, 30-day and all-time rankings differ.
            $favorite = Favorite::query()->firstOrCreate(['user_id' => $reader->id, 'story_id' => $story->id], ['notify_new_chapter' => false]);
            $favorite->forceFill(['created_at' => now()->subDays(($i * 3 + $story->id) % 40)])->save();
        }

        $story->forceFill(['favorite_count' => max($story->favorite_count, $count)])->saveQuietly();
    }

    /**
     * A story-level comment, a chapter comment and a reply, to see the shared thread.
     *
     * @param  list<User>  $readers
     */
    private function comments(Story $story, array $readers): void
    {
        if (Comment::query()->where('story_id', $story->id)->exists()) {
            return;
        }

        $chapter = $story->chapters()->orderBy('chapter_number')->first();

        $first = $this->comment($story, $readers[0], null, null, 'Truyện hay, mong tác giả ra chương đều tay!');
        $this->comment($story, $readers[1], $chapter?->id, null, 'Chương mở đầu cuốn thật, đoạn cuối làm mình tò mò.');
        $this->comment($story, $readers[2], null, $first->id, 'Đồng ý, mình cũng đang theo dõi truyện này.');

        $story->forceFill(['comment_count' => 3])->saveQuietly();
        if ($chapter) {
            $chapter->forceFill(['comment_count' => 1])->saveQuietly();
        }
    }

    private function comment(Story $story, User $user, ?int $chapterId, ?int $parentId, string $content): Comment
    {
        $comment = new Comment(['story_id' => $story->id, 'chapter_id' => $chapterId, 'parent_id' => $parentId, 'content' => $content]);
        $comment->user_id = $user->id;
        $comment->save();

        return $comment;
    }
}
