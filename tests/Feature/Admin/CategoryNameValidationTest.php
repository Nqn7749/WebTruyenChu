<?php

use App\Models\Category;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

    $this->admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'status' => true,
    ]);
});

test('a category name with angle brackets is rejected', function () {
    $this->actingAs($this->admin)->post(route('admin.categories.store'), [
        'name' => 'Fantasy</title><script>alert(1)</script>',
    ])->assertSessionHasErrors('name');

    expect(Category::query()->exists())->toBeFalse();
});

test('a tag name with angle brackets is rejected', function () {
    $this->actingAs($this->admin)->post(route('admin.tags.store'), [
        'name' => 'Tag<script>alert(1)</script>',
    ])->assertSessionHasErrors('name');

    expect(Tag::query()->exists())->toBeFalse();
});
