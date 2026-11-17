<?php

use App\Models\User;
use Illuminate\Support\Str;

test('unread endpoint returns only the authenticated users unread notifications', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownUnread = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Thông báo của tôi'],
    ]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Thông báo đã đọc'],
        'read_at' => now(),
    ]);

    $otherUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Thông báo người khác'],
    ]);

    $this->actingAs($user)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.id', $ownUnread->id)
        ->assertJsonPath('notifications.0.message', 'Thông báo của tôi')
        ->assertJsonMissing(['message' => 'Thông báo người khác']);
});

test('marking a notification read rejects an external redirect target', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [
            'message' => 'Liên kết không an toàn',
            'url' => 'https://attacker.example/phishing',
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', $notification->id))
        ->assertRedirect(route('notifications.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('marking a notification read allows a same-host internal path', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [
            'message' => 'Liên kết nội bộ',
            'url' => '/danh-sach-truyen?page=2',
        ],
    ]);

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', $notification->id))
        ->assertRedirect(url('/danh-sach-truyen?page=2'));
});

test('a user cannot mark another users notification as read', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Riêng tư'],
    ]);

    $this->actingAs($otherUser)
        ->patch(route('notifications.mark-read', $notification->id))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all as read only updates the authenticated users notifications', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownNotification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Của tôi'],
    ]);

    $otherNotification = $otherUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Của người khác'],
    ]);

    $this->actingAs($user)
        ->from(route('notifications.index'))
        ->patch(route('notifications.mark-all-read'))
        ->assertRedirect(route('notifications.index'));

    expect($ownNotification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});

test('notification list displays a fallback message when payload message is missing', function () {
    $user = User::factory()->create();
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'legacy',
        'data' => [],
    ]);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Bạn có thông báo mới.');
});
