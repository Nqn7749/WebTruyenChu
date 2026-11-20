<?php

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a verified member submits a story and it is published immediately', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện do thành viên đăng',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
        'status_publish' => true,
        'is_hot' => true,
    ])->assertRedirect(route('writer.stories.index'));

    $story = Story::query()->where('title', 'Truyện do thành viên đăng')->firstOrFail();

    // Tác giả tự đăng: truyện mới được công khai ngay. Kể cả khi gửi
    // status_publish=true hay is_hot=true, cờ nổi bật vẫn không được gắn từ
    // input: các trường đó do hệ thống/admin quyết định.
    expect($story->user_id)->toBe($user->id)
        ->and($story->status_publish)->toBeTrue()
        ->and($story->is_hot)->toBeFalse();
});

test('an admin can unpublish a story and publish it again', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => false]);

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-publish', $story))
        ->assertRedirect();

    expect($story->fresh()->status_publish)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-publish', $story))
        ->assertRedirect();

    expect($story->fresh()->status_publish)->toBeFalse();
});

test('a member can only manage their own stories', function () {
    $owner = User::factory()->create(['status' => true]);
    $otherUser = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($owner)->get(route('writer.stories.edit', $story))->assertForbidden();
    $this->actingAs($owner)->delete(route('writer.stories.destroy', $story))->assertForbidden();

    expect($story->fresh())->not->toBeNull();
});

test('editing a story does not change its publish status', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();
    $story = Story::factory()->create(['user_id' => $user->id, 'status_publish' => true]);

    $this->actingAs($user)->put(route('writer.stories.update', $story), [
        'title' => $story->title,
        'author_name' => $story->author_name,
        'description' => 'Nội dung vừa cập nhật',
        'status' => 'ongoing',
        'categories' => [$category->id],
        'status_publish' => false,
        'is_featured' => true,
    ])->assertRedirect(route('writer.stories.index'));

    expect($story->fresh()->status_publish)->toBeTrue()
        ->and($story->fresh()->is_featured)->toBeFalse();
});

test('a member can publish a chapter immediately', function () {
    $user = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $user->id, 'status_publish' => true]);

    $this->actingAs($user)->post(route('writer.stories.chapters.store', $story), [
        'chapter_number' => 1,
        'title' => 'Chương đầu',
        'content' => 'Nội dung chương',
        'status' => true,
    ])->assertRedirect(route('writer.stories.chapters.index', $story));

    $chapter = Chapter::query()->where('story_id', $story->id)->firstOrFail();
    expect($chapter->status)->toBeTrue();
});

test('a member cannot edit a chapter owned by another member', function () {
    $owner = User::factory()->create(['status' => true]);
    $otherUser = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $otherUser->id]);
    $chapter = Chapter::factory()->create(['story_id' => $story->id]);

    $this->actingAs($owner)->get(route('writer.chapters.edit', $chapter))->assertForbidden();
    $this->actingAs($owner)->delete(route('writer.chapters.destroy', $chapter))->assertForbidden();

    expect($chapter->fresh())->not->toBeNull();
});

test('a story without chapters shows a warning after seven days', function () {
    $user = User::factory()->create(['status' => true]);
    Story::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);

    $this->actingAs($user)
        ->get(route('writer.stories.index'))
        ->assertOk()
        ->assertSee('Vui lòng thêm chương trước khi đủ 30 ngày');
});

test('the cleanup command soft-deletes stories without chapters after thirty days', function () {
    $story = Story::factory()->create([
        'created_at' => now()->subDays(31),
        'updated_at' => now()->subDays(31),
    ]);

    $this->artisan('stories:delete-without-chapters')->assertSuccessful();

    $deletedStory = Story::withTrashed()->findOrFail($story->id);
    expect($deletedStory->trashed())->toBeTrue()
        ->and($deletedStory->deletion_reason)->toContain('30 ngày')
        ->and($deletedStory->deleted_by)->toBeNull();
});

test('the cleanup command keeps stories younger than thirty days', function () {
    $story = Story::factory()->create([
        'created_at' => now()->subDays(29),
        'updated_at' => now()->subDays(29),
    ]);

    $this->artisan('stories:delete-without-chapters')->assertSuccessful();

    expect($story->fresh())->not->toBeNull();
});

test('the cleanup command keeps stories that already have a chapter', function () {
    $story = Story::factory()->create([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);
    Chapter::factory()->create(['story_id' => $story->id]);

    $this->artisan('stories:delete-without-chapters')->assertSuccessful();

    expect($story->fresh())->not->toBeNull();
});

test('admin must provide a reason before deleting a story', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $story = Story::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.stories.destroy', $story))
        ->assertSessionHasErrors('deletion_reason');

    expect($story->fresh())->not->toBeNull();
});

test('admin deletion stores the reason and the author can see it', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id]);

    $this->actingAs($admin)
        ->delete(route('admin.stories.destroy', $story), [
            'deletion_reason' => 'Truyện vi phạm quy định nội dung của website.',
        ])
        ->assertRedirect(route('admin.stories.index'));

    $deletedStory = Story::withTrashed()->findOrFail($story->id);
    expect($deletedStory->trashed())->toBeTrue()
        ->and($deletedStory->deletion_reason)->toBe('Truyện vi phạm quy định nội dung của website.')
        ->and($deletedStory->deleted_by)->toBe($admin->id);

    $this->actingAs($author)
        ->get(route('writer.stories.index'))
        ->assertOk()
        ->assertSee('Truyện đã bị xóa.')
        ->assertSee('Truyện vi phạm quy định nội dung của website.');
});

test('admin story management does not expose create or edit routes', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);

    $this->actingAs($admin)->get('/admin/stories/create')->assertMethodNotAllowed();
    $this->actingAs($admin)->get('/admin/stories/1/edit')->assertNotFound();
});

test('editing a story title preserves its public slug', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();
    $story = Story::factory()->create([
        'user_id' => $user->id,
        'title' => 'Tên truyện cũ',
        'slug' => 'ten-truyen-cu',
    ]);

    $this->actingAs($user)->put(route('writer.stories.update', $story), [
        'title' => 'Tên truyện mới',
        'author_name' => $story->author_name,
        'description' => 'Mô tả cập nhật.',
        'status' => 'ongoing',
        'categories' => [$category->id],
    ])->assertRedirect(route('writer.stories.index'));

    expect($story->fresh()->title)->toBe('Tên truyện mới')
        ->and($story->fresh()->slug)->toBe('ten-truyen-cu');
});

test('a story cover upload rejects svg files', function () {
    Storage::fake('public');

    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    // File SVG thật (nội dung SVG + mime image/svg+xml): rule 'image' vẫn
    // cho qua, chỉ rule 'mimes' mới chặn — đúng lỗi H2 cần sửa.
    $path = tempnam(sys_get_temp_dir(), 'cover').'.svg';
    file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $svg = new UploadedFile($path, 'cover.svg', 'image/svg+xml', null, true);

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện có ảnh bìa SVG',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
        'cover_image' => $svg,
    ])->assertSessionHasErrors('cover_image');

    expect(Story::query()->where('title', 'Truyện có ảnh bìa SVG')->exists())->toBeFalse();

    if (file_exists($path)) {
        unlink($path);
    }
});

test('a story cover upload accepts png files', function () {
    Storage::fake('public');

    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    // Ảnh PNG 1x1 thật (không cần GD): đảm bảo rule 'mimes' không chặn
    // nhầm ảnh hợp lệ.
    $path = tempnam(sys_get_temp_dir(), 'cover').'.png';
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAwMCAO+ip1sAAAAASUVORK5CYII='));
    $png = new UploadedFile($path, 'cover.png', 'image/png', null, true);

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện có ảnh bìa PNG',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
        'cover_image' => $png,
    ])->assertRedirect(route('writer.stories.index'));

    $story = Story::query()->where('title', 'Truyện có ảnh bìa PNG')->firstOrFail();

    expect($story->cover_image)->not->toBeNull();
    Storage::disk('public')->assertExists($story->cover_image);
});
