<?php

use App\\Models\\Story;
use Illuminate\\Support\\Facades\\DB;

test('mysql full-text scope searches indexed story columns and exposes its index to the query planner', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-specific FULLTEXT verification.');
    }

    $match = Story::factory()->create([
        'title' => 'The Ember Chronicles',
        'description' => 'A journey through distant kingdoms.',
    ]);

    $results = Story::search('Ember')->pluck('id');

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
