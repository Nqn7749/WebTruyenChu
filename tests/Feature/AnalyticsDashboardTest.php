<?php

use App\Models\Chapter;
use App\Models\ReadingHistory;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;

test('admin dashboard shows system analytics and excludes unpublished stories from rankings', function () {
    $adminRole = Role::query()->firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Admin']
    );
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'status' => true,
    ]);
    $author = User::factory()->create(['status' => true]);
    $reader = User::factory()->create(['status' => true]);

    $publishedStory = Story::factory()->create([
        'user_id' => $author->id,
        'title' => 'Truyện nổi bật của tác giả',
        'status_publish' => true,
        'views' => 250,
    ]);
    $chapter = Chapter::factory()->create([
        'story_id' => $publishedStory->id,
        'chapter_number' => 1,
        'views' => 0,
    ]);
    Story::factory()->create([
        'user_id' => $author->id,
        'title' => 'Truyện chưa công khai',
        'status_publish' => false,
        'views' => 999,
    ]);

    ReadingHistory::query()->create([
        'user_id' => $reader->id,
        'story_id' => $publishedStory->id,
        'chapter_id' => $chapter->id,
        'read_at' => now()->subDays(2),
        'scroll_percent' => 50,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Tổng quan hệ thống')
        ->assertSee('Hoạt động đọc trong 30 ngày')
        ->assertSee('Truyện nổi bật của tác giả')
        ->assertDontSee('Truyện chưa công khai')
        ->assertSee('250');
});

test('writer analytics only count reading histories from stories owned by that writer', function () {
    $author = User::factory()->create(['status' => true]);
    $otherAuthor = User::factory()->create(['status' => true]);
    $reader = User::factory()->create(['status' => true]);

    $ownStory = Story::factory()->create([
        'user_id' => $author->id,
        'title' => 'Truyện của tôi',
        'status_publish' => true,
    ]);
    $otherStory = Story::factory()->create([
        'user_id' => $otherAuthor->id,
        'title' => 'Truyện người khác',
        'status_publish' => true,
    ]);
    $ownChapter = Chapter::factory()->create([
        'story_id' => $ownStory->id,
        'chapter_number' => 1,
        'views' => 0,
    ]);
    $otherChapter = Chapter::factory()->create([
        'story_id' => $otherStory->id,
        'chapter_number' => 1,
        'views' => 0,
    ]);

    ReadingHistory::query()->create([
        'user_id' => $reader->id,
        'story_id' => $ownStory->id,
        'chapter_id' => $ownChapter->id,
        'read_at' => now()->subDays(2),
        'scroll_percent' => 50,
    ]);
    ReadingHistory::query()->create([
        'user_id' => $reader->id,
        'story_id' => $otherStory->id,
        'chapter_id' => $otherChapter->id,
        'read_at' => now()->subDays(2),
        'scroll_percent' => 50,
    ]);

    $this->actingAs($author)
        ->get(route('writer.dashboard'))
        ->assertOk()
        ->assertSee('Hoạt động đọc trong 30 ngày')
        ->assertSee('Truyện của tôi')
        ->assertDontSee('Truyện người khác');
});
