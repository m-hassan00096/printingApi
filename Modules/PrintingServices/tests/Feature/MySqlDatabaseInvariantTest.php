<?php

namespace Modules\PrintingServices\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Modules\PrintingServices\Support\ServiceName;
use Tests\TestCase;

/**
 * Runs against disposable MySQL only. SQLite tests cannot prove InnoDB row
 * locking or the production unique index and case-sensitivity behavior.
 *
 * CI uses a dedicated MySQL database. Never point this suite at production.
 */
class MySqlDatabaseInvariantTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL/InnoDB is required for database invariants.');
        }
    }

    public function test_live_name_unique_index_allows_case_difference_and_soft_delete_reuse(): void
    {
        $first = $this->insertService('Business Cards');
        $lowercase = $this->insertService('business cards');
        $this->assertNotSame($first, $lowercase);

        // A Soft Delete clears the unique live-name key, but keeps the row.
        DB::table('printing_services')->where('id', $first)->update([
            'live_name_hash' => null,
            'deleted_at' => now(),
        ]);

        $new = $this->insertService('Business Cards');
        $this->assertNotSame($first, $new);
        $this->assertNotNull(DB::table('printing_services')->where('id', $first)->value('deleted_at'));
        $this->assertNull(DB::table('printing_services')->where('id', $new)->value('deleted_at'));
    }

    public function test_duplicate_live_name_is_rejected_by_the_mysql_unique_index(): void
    {
        $this->insertService('Glossy Cards');
        $this->expectException(QueryException::class);
        $this->insertService('Glossy Cards');
    }

    public function test_mysql_row_lock_blocks_a_second_connection_until_transaction_end(): void
    {
        $parameterId = DB::table('parameters')->insertGetId(['name' => 'customer_types']);
        $valueId = DB::table('parameter_values')->insertGetId([
            'parameter_id' => $parameterId,
            'name' => 'Wholesale',
            'status' => 1,
        ]);

        // Independent PDO/connection against the exact same disposable DB.
        Config::set('database.connections.printing_mysql_probe', Config::get('database.connections.mysql'));
        $primary = DB::connection();
        $probe = DB::connection('printing_mysql_probe');

        $primary->beginTransaction();

        try {
            $locked = $primary->table('parameter_values')
                ->where('id', $valueId)
                ->lockForUpdate()
                ->first();

            $this->assertNotNull($locked);

            $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $probe->beginTransaction();

            try {
                try {
                    $probe->table('parameter_values')->where('id', $valueId)
                        ->lockForUpdate()->first();

                    $this->fail('The second connection acquired an already-held InnoDB row lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                }
            } finally {
                $probe->rollBack();
            }
        } finally {
            $primary->rollBack();
            DB::purge('printing_mysql_probe');
        }
    }

    private function insertService(string $name): int
    {
        return DB::table('printing_services')->insertGetId([
            'name' => $name,
            'live_name_hash' => ServiceName::hash(ServiceName::normalize($name)),
            'status' => 1,
        ]);
    }
}

