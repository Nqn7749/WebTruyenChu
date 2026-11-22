<?php

declare(strict_types=1);

use App\Models\Chapter;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryUrlImportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function storyImportAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create([
        'role_id' => $role->id,
        'status' => true,
        'email_verified_at' => now(),
    ]);
}

test('admin can preview a story URL and its chapter links', function () {
    Cache::flush();
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Truyện thử"><meta name="author" content="Tác giả"><meta property="og:description" content="Giới thiệu"></head><body><a href="/chapter/1">Chương 1: Bắt đầu</a><a href="/chapter/2">Chương 2: Tiếp tục</a></body></html>'),
    ]);

    $this->actingAs(storyImportAdmin())
        ->followingRedirects()
        ->post(route('admin.story-import.preview'), ['url' => 'https://8.8.8.8/story'])
        ->assertOk()
        ->assertSee('Truyện thử')
        ->assertSee('Chương 1: Bắt đầu')
        ->assertSee('Tác giả');
});

test('admin imports selected chapters as unpublished drafts and prevents duplicate stories', function () {
    Cache::flush();
    Http::fake([
        'https://8.8.8.8/story' => Http::response('<html><head><meta property="og:title" content="Truyện thử"></head><body><a href="/chapter/1">Chương 1</a></body></html>'),
        'https://8.8.8.8/chapter/1' => Http::response('<html><body><article><p>'.str_repeat('Nội dung chương thử nghiệm. ', 8).'</p></article></body></html>'),
    ]);

    $admin = storyImportAdmin();
    $preview = app(StoryUrlImportService::class)->preview('https://8.8.8.8/story');
    $preview['requested_by'] = $admin->id;
    $token = str_repeat('a', 48);
    Cache::put('story-url-import:'.$token, $preview, now()->addMinutes(30));

    $this->actingAs($admin)
        ->post(route('admin.story-import.store'), [
            'token' => $token,
            'chapters' => [0],
            'permission_confirmed' => '1',
        ])->assertRedirect(route('admin.stories.index'));

    expect(Story::query()->where('source_hash', hash('sha256', 'https://8.8.8.8/story'))->exists())->toBeTrue()
        ->and(Chapter::query()->where('source_hash', hash('sha256', 'https://8.8.8.8/chapter/1'))->exists())->toBeTrue()
        ->and(Story::query()->first()->status_publish)->toBeFalse()
        ->and(Chapter::query()->first()->status)->toBeFalse();

    $this->post(route('admin.story-import.store'), [
        'token' => $token,
        'chapters' => [0],
        'permission_confirmed' => '1',
    ])->assertRedirect(route('admin.story-import.index'))
        ->assertSessionHasErrors('url');

    expect(Story::query()->count())->toBe(1);
});

test('story URL import rejects insecure URLs', function () {
    $this->actingAs(storyImportAdmin())
        ->post(route('admin.story-import.preview'), ['url' => 'http://8.8.8.8/story'])
        ->assertSessionHasErrors('url');
});
