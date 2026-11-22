<?php

use App\Models\Comment;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('posting a comment while another post is in progress is rejected', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);

    // Mô phỏng 2 request đăng cùng lúc: giữ khóa theo user trước
    // rồi mới post — controller phải trả 429 thay vì cùng insert (F4).
    $lock = Cache::lock('comment-post-'.$user->id, 10);
    $lock->acquire();

    try {
        $this->actingAs($user)
            ->postJson(route('comments.store', $story), ['content' => 'Bình luận.'])
            ->assertStatus(429);
    } finally {
        $lock->release();
    }

    // Nhả khóa ra thì đăng lại được bình thường.
    $this->actingAs($user)
        ->postJson(route('comments.store', $story), ['content' => 'Bình luận.'])
        ->assertOk();

    expect(Comment::query()->where('story_id', $story->id)->count())->toBe(1);
});

test('posting the same content with different spacing or casing is rejected as duplicate', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create(['status_publish' => true]);

    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'story_id' => $story->id,
        'content' => 'Bình luận hay.',
    ]);
    $comment->forceFill(['created_at' => now()->subMinutes(1)])->saveQuietly();

    // Thừa khoảng trắng + khác hoa thường nhưng sau chuẩn hóa vẫn là
    // cùng một nội dung trong 10 phút gần nhất → 422 (C1).
    $this->actingAs($user)
        ->postJson(route('comments.store', $story), ['content' => '  BÌNH LUẬN   hay.  '])
        ->assertUnprocessable();

    expect(Comment::query()->where('story_id', $story->id)->count())->toBe(1);
});

test('commenting on a different story within thirty seconds is rejected by the global cooldown', function () {
    $user = User::factory()->create();
    $storyA = Story::factory()->create(['status_publish' => true]);
    $storyB = Story::factory()->create(['status_publish' => true]);

    $this->actingAs($user)
        ->postJson(route('comments.store', $storyA), ['content' => 'Bình luận truyện A.'])
        ->assertOk();

    // Cooldown 30 giây tính theo user, không phân biệt truyện (C2):
    // đổi truyện khác không còn bypass được nữa.
    $this->actingAs($user)
        ->postJson(route('comments.store', $storyB), ['content' => 'Bình luận truyện B.'])
        ->assertStatus(429);

    expect(Comment::query()->count())->toBe(1);
});

test('search with an array q parameter returns the search page instead of redirecting', function () {
    Story::factory()->create(['status_publish' => true]);

    $response = $this->get(route('search').'?q[]=x');

    $response->assertOk();
    $response->assertViewIs('search.index');
});

test('search page abc and page 1 return the same results', function () {
    Story::factory()->create(['status_publish' => true, 'title' => 'Truyện kiểm tra trang']);

    commitFullTextFixtures();

    $first = $this->get(route('search', ['q' => 'kiểm tra', 'page' => 1]));
    $second = $this->get(route('search', ['q' => 'kiểm tra', 'page' => 'abc']));

    $first->assertOk()->assertSee('Truyện kiểm tra trang');
    $second->assertOk()->assertSee('Truyện kiểm tra trang');

    // Cache key đã normalize page (C7) nên cả hai trả cùng một nội dung.
    expect($second->getContent())->toBe($first->getContent());
});

test('admin activity logs with an array action parameter does not error', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);

    $this->actingAs($admin)
        ->get(route('admin.activity-logs.index').'?action[]=x')
        ->assertOk();
});

test('story page canonical excludes unrelated search parameters', function () {
    $story = Story::factory()->create(['status_publish' => true]);

    $response = $this->get(route('stories.show', $story).'?q=kiem&sort=top_rated&page=2');

    $response->assertOk();
    $response->assertSee('<link rel="canonical" href="'.route('stories.show', $story).'?page=2"', false);
});
