<?php
namespace Modules\Orders\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Services\OrderNumberGenerator;
use Tests\TestCase;

/** DESTRUCTIVE: NEVER run against development/production data. */
class MySqlOrderInvariantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL/InnoDB is required.');
        }

        if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_ci')) {
            $this->markTestSkipped('Refusing to recreate a non-CI MySQL database.');
        }

        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
    }

    public function test_daily_sequence_generates_distinct_confirm_numbers_under_transaction(): void
    {
        $first = DB::transaction(fn () => app(OrderNumberGenerator::class)->next());
        $second = DB::transaction(fn () => app(OrderNumberGenerator::class)->next());

        $this->assertMatchesRegularExpression('/^ORD-[0-9]{6}001$/', $first);
        $this->assertSame(substr($first, 0, -3).'002', $second);
        $this->assertDatabaseCount('order_daily_sequences', 1);
    }

    public function test_customer_row_lock_is_exclusive_across_independent_connections(): void
    {
        $parameterId = DB::table('parameters')->insertGetId(['name' => 'customer_types']);
        $typeId = DB::table('parameter_values')->insertGetId([
            'parameter_id' => $parameterId, 'name' => 'Wholesale', 'status' => 1,
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'MySQL Customer', 'phone' => '01012345678',
            'live_phone_hash' => hash('sha256', '01012345678'),
            'customer_type_id' => $typeId, 'status' => 1,
        ]);

        $primary = DB::connection();
        Config::set('database.connections.orders_mysql_probe', Config::get('database.connections.mysql'));
        $probe = DB::connection('orders_mysql_probe');
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
                    $this->fail('Second connection acquired an already locked customer row.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                }
            } finally {
                $probe->rollBack();
            }
        } finally {
            $primary->rollBack();
            DB::purge('orders_mysql_probe');
        }
    }
}
