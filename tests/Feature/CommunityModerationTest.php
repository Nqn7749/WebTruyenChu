<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Report;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_report_a_story_and_duplicate_pending_report_is_not_created(): void
    {
        $memberRole = Role::factory()->create(['name' => 'Thành viên', 'slug' => 'member']);
        $author = User::factory()->create(['role_id' => $memberRole->id]);
        $reporter = User::factory()->create(['role_id' => $memberRole->id]);
        $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);

        $this->actingAs($reporter)->post(route('reports.stories.store', $story), [
            'reason' => 'spam',
            'details' => 'Nội dung quảng cáo không liên quan.',
        ])->assertRedirect();

        $this->actingAs($reporter)->post(route('reports.stories.store', $story), [
            'reason' => 'spam',
        ])->assertRedirect();

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', ['reporter_id' => $reporter->id, 'status' => 'pending']);
    }

    public function test_member_cannot_report_own_story(): void
    {
        $role = Role::factory()->create(['name' => 'Thành viên', 'slug' => 'member']);
        $author = User::factory()->create(['role_id' => $role->id]);
        $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);

        $this->actingAs($author)
            ->post(route('reports.stories.store', $story), ['reason' => 'spam'])
            ->assertForbidden();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_admin_can_hide_reported_comment_and_action_is_logged(): void
    {
        $memberRole = Role::factory()->create(['name' => 'Thành viên', 'slug' => 'member']);
        $adminRole = Role::factory()->create(['name' => 'Quản trị viên', 'slug' => 'admin']);
        $author = User::factory()->create(['role_id' => $memberRole->id]);
        $reporter = User::factory()->create(['role_id' => $memberRole->id]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $story = Story::factory()->create(['user_id' => $author->id, 'status_publish' => true]);
        $comment = Comment::factory()->create([
            'user_id' => $author->id,
            'story_id' => $story->id,
            'is_hidden' => false,
        ]);
        $report = $comment->reports()->create([
            'reporter_id' => $reporter->id,
            'reason' => 'abuse',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch(route('admin.reports.update', $report), [
            'action' => 'hide_comment',
            'resolution_note' => 'Bình luận vi phạm quy tắc cộng đồng.',
        ])->assertRedirect();

        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'is_hidden' => true]);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'resolved']);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $admin->id,
            'action' => 'report.resolved',
        ]);
    }

    public function test_non_admin_cannot_access_moderation_queue(): void
    {
        $role = Role::factory()->create(['name' => 'Thành viên', 'slug' => 'member']);
        $member = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($member)->get(route('admin.reports.index'))->assertForbidden();
    }
}
