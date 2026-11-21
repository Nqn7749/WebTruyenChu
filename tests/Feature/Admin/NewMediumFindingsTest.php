<?php

use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;

test('N-M2: story list with an array chapters query does not crash', function () {
    Story::factory()->create(['status_publish' => true]);

    $this->get('/danh-sach-truyen?chapters[]=x')->assertOk();
});

test('N-M1: canonical keeps chapter_page and chapters query parameters', function () {
    Story::factory()->create(['status_publish' => true]);

    $this->get('/danh-sach-truyen?chapter_page=2&chapters=300_600')
        ->assertOk()
        ->assertSee('chapter_page=2', false)
        ->assertSee('chapters=300_600', false);
});

test('N-M4: a story meta_description over 20,000 characters is rejected', function () {
    $user = User::factory()->create(['status' => true]);
    $category = Category::factory()->create();

    $this->actingAs($user)->post(route('writer.stories.store'), [
        'title' => 'Truyện mô tả meta quá dài',
        'author_name' => 'Bút danh',
        'description' => 'Giới thiệu truyện',
        'status' => 'ongoing',
        'categories' => [$category->id],
        // Tiếng Việt ~3 byte/ký tự: 20.001 ký tự đã vượt 65.535 byte của
        // cột TEXT, trước đây rule max:60000 cho qua rồi MySQL báo 500.
        'meta_description' => str_repeat('à', 20001),
    ])->assertSessionHasErrors('meta_description');

    expect(Story::query()->where('title', 'Truyện mô tả meta quá dài')->exists())->toBeFalse();
});

test('N-M5: admin activity log truncates a description longer than 1000 characters', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $request = Request::create('/admin/stories/'.$story->id, 'DELETE');

    app(AdminActivityLogger::class)->record(
        $request,
        'story.deleted',
        $story,
        'Xóa truyện "'.$story->title.'": '.str_repeat('lý do dài ', 200)
    );

    $log = AdminActivityLog::query()->firstOrFail();

    expect(mb_strlen($log->description))->toBeLessThanOrEqual(1000);
});

test('N-M6: an admin can toggle a story hot, featured and recommended flag', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $story = Story::factory()->create([
        'is_hot' => false,
        'is_featured' => false,
        'is_recommended' => false,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'is_hot'])
        ->assertRedirect();

    expect($story->fresh()->is_hot)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'is_hot'])
        ->assertRedirect();

    expect($story->fresh()->is_hot)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'is_featured'])
        ->assertRedirect();

    expect($story->fresh()->is_featured)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'is_recommended'])
        ->assertRedirect();

    expect($story->fresh()->is_recommended)->toBeTrue();
});

test('N-M6: toggling an unknown flag is rejected', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
    $story = Story::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.stories.toggle-flag', $story), ['flag' => 'status_publish'])
        ->assertSessionHasErrors('flag');
});
