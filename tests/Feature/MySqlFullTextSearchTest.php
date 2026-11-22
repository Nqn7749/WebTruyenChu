<?php

use App\Models\Story;
use Illuminate\Support\Facades\DB;

test('mysql full-text index matches indexed story columns and exposes its index to the query planner', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-specific FULLTEXT verification.');
    }

    $match = Story::factory()->create([
        'title' => 'The Ember Chronicles',
        'description' => 'A journey through distant kingdoms.',
    ]);

    // InnoDB publishes FULLTEXT index changes at transaction commit. The
    // Feature suite's RefreshDatabase transaction would otherwise hide the
    // newly inserted row from MATCH ... AGAINST in this integration test.
    commitFullTextFixtures();

    // scopeSearch đã bị xóa (V6, giữ semantics OR cũ): gọi thẳng
    // whereFullText natural-language mode để kiểm chứng index.
    $results = Story::query()->whereFullText(['title', 'description'], 'Ember')->pluck('id');

    expect($results)->toContain($match->id);

    $indexes = DB::select("SHOW INDEX FROM stories WHERE Index_type = 'FULLTEXT'");
    $indexedColumns = collect($indexes)->pluck('Column_name')->all();

    expect($indexedColumns)->toContain('title')
        ->and($indexedColumns)->toContain('description');

    $plan = DB::select(
        'EXPLAIN SELECT id FROM stories WHERE MATCH(title, description) AGAINST (? IN NATURAL LANGUAGE MODE)',
        ['Ember']
    );

    expect((string) $plan[0]->possible_keys)
        ->toContain('stories_title_description_fulltext');
});

test('mysql full-text search requires every keyword word to match', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-specific FULLTEXT verification.');
    }

    $both = Story::factory()->create([
        'title' => 'Kiếm hiệp giang hồ',
        'description' => 'Truyện kiếm hiệp.',
        'status_publish' => true,
    ]);
    $onlyFirst = Story::factory()->create([
        'title' => 'Kiếm khách độc hành',
        'description' => 'Chỉ có kiếm.',
        'status_publish' => true,
    ]);
    $onlySecond = Story::factory()->create([
        'title' => 'Hiệp sĩ bóng đêm',
        'description' => 'Chỉ có hiệp.',
        'status_publish' => true,
    ]);

    // InnoDB publishes FULLTEXT index changes at transaction commit. The
    // Feature suite's RefreshDatabase transaction would otherwise hide the
    // newly inserted row from MATCH ... AGAINST in this integration test.
    commitFullTextFixtures();

    $response = $this->get('/tim-kiem?q='.urlencode('kiếm hiệp'));

    $response->assertOk();
    $response->assertSee($both->title);
    $response->assertDontSee($onlyFirst->title);
    $response->assertDontSee($onlySecond->title);
});

test('mysql search falls back to substring matching for stopword-only keywords', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-specific FULLTEXT verification.');
    }

    $match = Story::factory()->create([
        'title' => 'About Ma',
        'description' => 'A story about Ma.',
        'status_publish' => true,
    ]);

    // InnoDB publishes FULLTEXT index changes at transaction commit. The
    // Feature suite's RefreshDatabase transaction would otherwise hide the
    // newly inserted row from MATCH ... AGAINST in this integration test.
    commitFullTextFixtures();

    $response = $this->get('/tim-kiem?q='.urlencode('about ma'));

    $response->assertOk();
    $response->assertSee($match->title);
});

test('mysql full-text search matches a partial word via prefix matching', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-specific FULLTEXT verification.');
    }

    $match = Story::factory()->create([
        'title' => 'Truyện kiếm hiệp',
        'description' => 'Một câu chuyện giang hồ.',
        'status_publish' => true,
    ]);

    // InnoDB publishes FULLTEXT index changes at transaction commit. The
    // Feature suite's RefreshDatabase transaction would otherwise hide the
    // newly inserted row from MATCH ... AGAINST in this integration test.
    commitFullTextFixtures();

    $response = $this->get('/tim-kiem?q='.urlencode('truy'));

    $response->assertOk();
    $response->assertSee($match->title);
});
