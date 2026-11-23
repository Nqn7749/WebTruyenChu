<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\Story;
use App\Models\User;

function dashboardAdmin(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

    return User::factory()->create(['role_id' => $role->id, 'status' => true, 'email_verified_at' => now()]);
}

test('the top stories and top authors cards show five rows and a show-more button for the rest', function () {
    foreach (range(1, 8) as $i) {
        $author = User::factory()->create(['name' => 'Tác giả số '.$i]);
        Story::factory()->create(['user_id' => $author->id, 'status_publish' => true, 'title' => 'Truyện số '.$i, 'views' => 1000 - $i]);
    }

    $html = $this->actingAs(dashboardAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();

    // Eight rows are rendered per card: the first five visible, three hidden behind the button.
    expect(substr_count($html, 'data-more="stories"'))->toBe(3)
        ->and(substr_count($html, 'data-more="authors"'))->toBe(3)
        ->and($html)->toContain('data-more-toggle="stories"')
        ->toContain('data-more-toggle="authors"')
        ->toContain('Xem thêm (3)')
        ->toContain('Truyện số 8');
});

test('no show-more button appears when there are five rows or fewer', function () {
    foreach (range(1, 4) as $i) {
        Story::factory()->create(['user_id' => User::factory()->create()->id, 'status_publish' => true]);
    }

    $html = $this->actingAs(dashboardAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();

    // (The page script always mentions the attribute name, so look for the buttons and hidden rows.)
    expect($html)->not->toContain('data-more-toggle="stories"')
        ->not->toContain('data-more-toggle="authors"')
        ->not->toContain('data-more="stories"')
        ->not->toContain('data-more="authors"');
});

test('each card loads at most twenty rows', function () {
    foreach (range(1, 25) as $i) {
        Story::factory()->create(['user_id' => User::factory()->create()->id, 'status_publish' => true, 'views' => 100 + $i]);
    }

    $html = $this->actingAs(dashboardAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();

    // 20 loaded, 5 shown, 15 hidden.
    expect(substr_count($html, 'data-more="stories"'))->toBe(15)
        ->and(substr_count($html, 'data-more="authors"'))->toBe(15)
        ->and($html)->toContain('Xem thêm (15)');
});

test('the hidden rows keep the ranking order', function () {
    foreach (range(1, 7) as $i) {
        Story::factory()->create(['user_id' => User::factory()->create()->id, 'status_publish' => true, 'title' => 'Hạng '.$i, 'views' => 10_000 - $i]);
    }

    $html = $this->actingAs(dashboardAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();

    expect(strpos($html, 'Hạng 1'))->toBeLessThan(strpos($html, 'Hạng 5'))
        ->and(strpos($html, 'Hạng 5'))->toBeLessThan(strpos($html, 'Hạng 7'));
});
