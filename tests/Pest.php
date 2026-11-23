<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * MySQL publishes FULLTEXT index changes only at commit, so FULLTEXT tests must
 * commit their fixtures. Those rows would survive RefreshDatabase's rollback and
 * leak into later tests, so they are purged once the test has finished.
 */
function commitFullTextFixtures(): void
{
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'mysql') {
        return;
    }

    // The cleanup below deletes every row, so never run it against a non-test database.
    if (! app()->environment('testing') || ! str_contains(strtolower((string) $connection->getDatabaseName()), 'test')) {
        throw new RuntimeException('commitFullTextFixtures() refuses to run: database "'.$connection->getDatabaseName().'" is not a test database.');
    }
    if ($connection->transactionLevel() !== 1) {
        throw new RuntimeException('commitFullTextFixtures() expects exactly one open transaction, found '.$connection->transactionLevel().'.');
    }

    $connection->commit();
    $connection->beginTransaction();

    test()->beforeApplicationDestroyed(function (): void {
        $connection = DB::connection();
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($connection->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $table = array_values((array) $row)[0];
            if ($table !== 'migrations') {
                $connection->table($table)->delete();
            }
        }
        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    });
}

function something()
{
    // ..
}
