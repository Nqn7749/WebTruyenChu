<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;

test('deleted user comments do not break story and chapter pages', function () {
    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    $commenter = User::factory()->create();
    $replier = User::factory()->create();

    $comment = Comment::factory()->for($story)->create(['user_id' => $commenter->id]);
    Comment::factory()->for($story)->create([
        'user_id' => $replier->id,
        'parent_id' => $comment->id,
    ]);
    Comment::factory()->for($story)->create([
        'user_id' => $commenter->id,
        'chapter_id' => $chapter->id,
    ]);

    // Người bình luận tự xóa tài khoản của mình (không có truyện nên
    // được xóa). Bình thường FK cascadeOnDelete sẽ xóa bình luận cùng
    // tài khoản; test này khóa hành trình đầu-cuối: sau khi xóa tài
    // khoản, trang truyện và trang chương vẫn phải trả 200.
    $this->actingAs($commenter)
        ->delete('/profile', ['password' => 'Password1234'])
        ->assertRedirect('/');
    $this->actingAs($replier)
        ->delete('/profile', ['password' => 'Password1234'])
        ->assertRedirect('/');

    expect($commenter->fresh())->toBeNull()
        ->and($replier->fresh())->toBeNull();

    $this->get('/truyen/'.$story->slug)->assertOk();
    $this->get('/truyen/'.$story->slug.'/chuong/'.$chapter->slug)->assertOk();
});

test('orphaned comment renders as deleted member instead of 500', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $comment = Comment::factory()->for($story)->create();
    $reply = Comment::factory()->for($story)->create(['parent_id' => $comment->id]);

    // Giả lập bình luận mồ côi: tài khoản đã mất nhưng bình luận còn
    // (dữ liệu cũ/nhập tay, DB không bật FK). Trong test SQLite chạy
    // trong transaction nên không tắt được FK bằng PRAGMA để tạo trạng
    // thái này bằng SQL — set relation null trực tiếp thay cho việc đó.
    // Không có fix null-safe, render() sẽ ném lỗi đọc thuộc tính name
    // trên null.
    $comment->setRelation('user', null);
    $reply->setRelation('user', null);
    $comment->setRelation('replies', collect([$reply]));

    $html = view('components.comment', ['comment' => $comment])->render();

    expect($html)->toContain('Thành viên đã xóa');
});

test('force-deleting a story removes its comments', function () {
    $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);

    $author = User::factory()->create(['status' => true]);
    $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    Comment::factory()->for($story)->create();
    Comment::factory()->for($story)->create(['chapter_id' => $chapter->id]);
    $hidden = Comment::factory()->for($story)->create();
    $hidden->delete(); // Bình luận đã xóa mềm cũng phải bị xóa hẳn.

    $this->actingAs($admin)
        ->delete(
            route('admin.stories.force-destroy', ['story' => $story->id]),
            ['deletion_reason' => 'Truyện vi phạm bản quyền nội dung.']
        )
        ->assertRedirect(route('admin.stories.index'));

    expect(Story::withTrashed()->find($story->id))->toBeNull()
        ->and(Comment::withTrashed()->where('story_id', $story->id)->count())->toBe(0);

    // Trang kiểm duyệt bình luận không được 500 vì truyện đã mất.
    $this->actingAs($admin)->get(route('admin.comments.index'))->assertOk();
});
