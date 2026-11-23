<?php

use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Models\User;

test('each parent comment shows up to twenty replies on the story page', function () {
    $story = Story::factory()->create(['status_publish' => true]);

    $busyParent = Comment::factory()->for($story)->create(['content' => 'Cha bận rộn.']);
    foreach (range(1, 25) as $i) {
        Comment::factory()->for($story)->create([
            'parent_id' => $busyParent->id,
            'content' => 'Reply bận rộn #'.$i,
        ]);
    }

    $quietParent = Comment::factory()->for($story)->create(['content' => 'Cha yên tĩnh.']);
    foreach (range(1, 5) as $i) {
        Comment::factory()->for($story)->create([
            'parent_id' => $quietParent->id,
            'content' => 'Reply yên tĩnh #'.$i,
        ]);
    }

    $response = $this->get(route('stories.show', $story));

    $response->assertOk();
    $response->assertSee('Cha bận rộn.');
    $response->assertSee('Cha yên tĩnh.');
    $response->assertSee('Reply bận rộn #1');
    $response->assertSee('Reply bận rộn #20');
    $response->assertDontSee('Reply bận rộn #21');
    $response->assertDontSee('Reply bận rộn #25');
    $response->assertSee('Reply yên tĩnh #5');
});

test('each parent comment shows up to twenty replies on the chapter page', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);

    $busyParent = Comment::factory()->for($story)->create([
        'chapter_id' => $chapter->id,
        'content' => 'Cha bận rộn chương.',
    ]);
    foreach (range(1, 25) as $i) {
        Comment::factory()->for($story)->create([
            'chapter_id' => $chapter->id,
            'parent_id' => $busyParent->id,
            'content' => 'Reply chương #'.$i,
        ]);
    }

    $response = $this->get(route('chapters.show', [$story, $chapter]));

    $response->assertOk();
    $response->assertSee('Cha bận rộn chương.');
    $response->assertSee('Reply chương #20');
    $response->assertDontSee('Reply chương #21');
});

test('story page shows chapter comments with their chapter badge, but not those of draft or deleted chapters', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $published = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 7]);
    $draft = Chapter::factory()->for($story)->create(['status' => false]);
    $deleted = Chapter::factory()->for($story)->create(['status' => true]);

    Comment::factory()->for($story)->create(['content' => 'Bình luận cấp truyện.']);
    Comment::factory()->for($story)->create(['chapter_id' => $published->id, 'content' => 'Bình luận trong chương công khai.']);
    Comment::factory()->for($story)->create(['chapter_id' => $draft->id, 'content' => 'Bình luận chương nháp.']);
    Comment::factory()->for($story)->create(['chapter_id' => $deleted->id, 'content' => 'Bình luận chương đã xóa.']);
    $deleted->delete();

    $this->get(route('stories.show', $story))
        ->assertOk()
        ->assertSee('Bình luận cấp truyện.')
        ->assertSee('Bình luận trong chương công khai.')
        ->assertSee('Chương 7')
        ->assertDontSee('Bình luận chương nháp.')
        ->assertDontSee('Bình luận chương đã xóa.');
});

test('a comment posted inside a chapter appears on the story page and in that chapter', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('comments.store', $story), ['chapter_id' => $chapter->id, 'content' => 'Đồng bộ hai nơi.'])
        ->assertSessionHasNoErrors();

    $this->get(route('stories.show', $story))->assertSee('Đồng bộ hai nơi.');
    $this->get(route('chapters.show', [$story, $chapter]))->assertSee('Đồng bộ hai nơi.');
});

test('chapter page also shows story-level and other chapters comments but not draft chapter ones', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $current = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 1]);
    $other = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 2]);
    $draft = Chapter::factory()->for($story)->create(['status' => false]);

    Comment::factory()->for($story)->create(['content' => 'Ngoài truyện.']);
    Comment::factory()->for($story)->create(['chapter_id' => $other->id, 'content' => 'Ở chương khác.']);
    Comment::factory()->for($story)->create(['chapter_id' => $draft->id, 'content' => 'Ở chương nháp.']);

    $this->get(route('chapters.show', [$story, $current->slug]))
        ->assertOk()
        ->assertSee('Ngoài truyện.')
        ->assertSee('Ở chương khác.')
        ->assertSee('Chương 2')
        ->assertDontSee('Ở chương nháp.');
});

test('story page offers a jump-to-chapter form that redirects to the published chapter', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    $chapter = Chapter::factory()->for($story)->create(['status' => true, 'chapter_number' => 150]);
    Chapter::factory()->for($story)->create(['status' => false, 'chapter_number' => 151]);

    $this->get(route('stories.show', $story))->assertOk()->assertSee('Nhảy tới chương');

    $this->get(route('chapters.jump', [$story, 'so' => 150]))
        ->assertRedirect(route('chapters.show', [$story, $chapter->slug]));
});

test('jump-to-chapter rejects missing, draft and invalid chapter numbers', function () {
    $story = Story::factory()->create(['status_publish' => true]);
    Chapter::factory()->for($story)->create(['status' => false, 'chapter_number' => 5]);

    $this->get(route('chapters.jump', [$story, 'so' => 5]))
        ->assertRedirect(route('stories.show', $story).'#chapterList')
        ->assertSessionHas('chapter_jump_error', 'Không có chương số 5.');

    $this->get(route('chapters.jump', [$story, 'so' => 'abc']))->assertSessionHasErrors('so');
    $this->get(route('chapters.jump', [$story, 'so' => 0]))->assertSessionHasErrors('so');
});

test('jump-to-chapter is not available for unpublished stories', function () {
    $story = Story::factory()->create(['status_publish' => false]);

    $this->get(route('chapters.jump', [$story, 'so' => 1]))->assertNotFound();
});
