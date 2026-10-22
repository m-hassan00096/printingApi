<?php

namespace Modules\Customers\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Services\CustomerTypeAssignmentService;
use Modules\Customers\Support\CustomerPhone;
use Tests\TestCase;

/**
 * MySQL-only invariants: SQLite cannot prove InnoDB row locks or the exact
 * production unique-index semantics.
 *
 * DESTRUCTIVE: migrate:fresh is permitted only on disposable *_ci databases.
 * Never point this class at a development or production database.
 */
class MySqlCustomerInvariantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL/InnoDB is required for customer invariants.');
        }

        $database = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_ci')) {
            $this->markTestSkipped('Refusing to recreate a non-CI MySQL database.');
        }

        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
    }

    public function test_mysql_enforces_binary_exact_live_phone_uniqueness(): void
    {
        $typeId = $this->insertCustomerType();
        $firstId = $this->insertCustomer($typeId, '01012345678');

        // Interior whitespace is significant; the two phones are different.
        $secondId = $this->insertCustomer($typeId, '010 12345678');

        $this->assertNotSame($firstId, $secondId);

        try {
            // Trimming at the edges produces the exact same phone hash.
            $this->insertCustomer($typeId, ' 01012345678 ');
            $this->fail('MySQL accepted a duplicate active customer phone.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, (int) ($exception->errorInfo[1] ?? 0));
        }
    }

    public function test_mysql_soft_delete_frees_phone_without_deleting_history(): void
    {
        $typeId = $this->insertCustomerType();
        $oldId = $this->insertCustomer($typeId, '+20-01012345678');

        DB::table('customers')->where('id', $oldId)->update([
            'live_phone_hash' => null,
            'deleted_at' => now(),
        ]);

        $newId = $this->insertCustomer($typeId, '+20-01012345678');

        $this->assertNotSame($oldId, $newId);
        $this->assertNotNull(DB::table('customers')->where('id', $oldId)->value('deleted_at'));
        $this->assertNull(DB::table('customers')->where('id', $newId)->value('deleted_at'));
    }

    public function test_mysql_type_assignment_locks_the_same_row_as_settings_changes(): void
    {
        $typeId = $this->insertCustomerType();

        $primary = DB::connection();
        Config::set('database.connections.customers_mysql_probe', Config::get('database.connections.mysql'));
        $probe = DB::connection('customers_mysql_probe');

        $primary->beginTransaction();

        try {
            app(CustomerTypeAssignmentService::class)->assertEligibleAndLock($typeId);

            $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $probe->beginTransaction();

            try {
                try {
                    $probe->table('parameter_values')->where('id', $typeId)
                        ->lockForUpdate()->first();

                    $this->fail('Settings acquired a customer-type row already locked by customer assignment.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                }
            } finally {
                $probe->rollBack();
            }
        } finally {
            $primary->rollBack();
            DB::purge('customers_mysql_probe');
        }
    }

    public function test_mysql_customer_row_lock_can_serialize_future_orders_and_finance(): void
    {
        $typeId = $this->insertCustomerType();
        $customerId = $this->insertCustomer($typeId, '01111111111');

        $primary = DB::connection();
        Config::set('database.connections.customers_mysql_probe', Config::get('database.connections.mysql'));
        $probe = DB::connection('customers_mysql_probe');

        $primary->beginTransaction();

        try {
            $this->assertNotNull($primary->table('customers')
                ->where('id', $customerId)->lockForUpdate()->first());

            $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $probe->beginTransaction();

            try {
                try {
                    $probe->table('customers')->where('id', $customerId)
                        ->lockForUpdate()->first();

                    $this->fail('A concurrent connection acquired a locked customer row.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                }
            } finally {
                $probe->rollBack();
            }
        } finally {
            $primary->rollBack();
            DB::purge('customers_mysql_probe');
        }
    }

    private function insertCustomerType(): int
    {
        $groupId = DB::table('parameters')->insertGetId(['name' => 'customer_types']);

        return DB::table('parameter_values')->insertGetId([
            'parameter_id' => $groupId,
            'name' => 'Wholesale',
            'status' => 1,
        ]);
    }

    private function insertCustomer(int $typeId, string $phone): int
    {
        $normalizedPhone = CustomerPhone::normalize($phone);

        return DB::table('customers')->insertGetId([
            'name' => 'MySQL customer',
            'phone' => $normalizedPhone,
            'live_phone_hash' => CustomerPhone::hash($normalizedPhone),
            'customer_type_id' => $typeId,
            'status' => 1,
        ]);
    }
}
