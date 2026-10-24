<?php
namespace Modules\Deliveries\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Deliveries\Contracts\DeliveryOrderGateway;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Services\LedgerService;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

/**
 * Unlike RefreshDatabase's usual wrapping transaction, these MySQL-only
 * tests commit their fixtures so two independent DB sessions can see them.
 */
class MySqlDeliveryConcurrencyTest extends OrdersTestCase
{
    public function refreshDatabase(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->artisan('migrate:fresh', ['--force' => true]);
            return;
        }
        parent::refreshDatabase();
    }

    private function secondConnection(): \Illuminate\Database\Connection
    {
        config()->set('database.connections.delivery_secondary', config('database.connections.mysql'));
        DB::purge('delivery_secondary');
        return DB::connection('delivery_secondary');
    }

    public function test_posting_balance_is_a_current_read_after_another_connection_commits(): void
    {
        if (DB::getDriverName() !== 'mysql') $this->markTestSkipped('MySQL 8 isolation integration test.');

        $order = $this->draft();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders/'.$order->id.'/confirm', [])->assertOk();

        $customerId = $this->customer->id;
        $ledger = app(LedgerService::class);
        $first = DB::connection();
        $second = $this->secondConnection();

        $first->beginTransaction();
        try {
            // Starts a REPEATABLE READ consistent-read snapshot BEFORE the other posting.
            $this->assertSame(1, $first->table('finance_ledger_entries')
                ->where('customer_id', $customerId)->count());
            $second->table('finance_ledger_entries')->insert([
                'customer_id' => $customerId,
                'type' => LedgerEntryType::COLLECTION->value,
                'signed_amount' => '-340.00',
                'event_key' => 'concurrent-customer-collection-'.$customerId,
                'order_id' => null, 'collection_id' => null, 'refund_id' => null,
                'reverses_entry_id' => null, 'effective_date' => now('Africa/Cairo')->toDateString(),
                'note' => 'Simulated committed concurrent collection (test only)',
                'created_by' => $this->admin->id, 'created_at' => now(),
            ]);

            // Plain SUM sees old snapshot; posting must use fresh locking read.
            $this->assertSame(35000, $ledger->cents($customerId));
            $ledger->lockCustomer($customerId);
            $this->assertSame(1000, $ledger->centsForPosting($customerId));
        } finally {
            $first->rollBack();
            DB::disconnect('delivery_secondary');
        }
    }

    public function test_order_lock_blocks_a_second_independent_delivery_gateway_connection(): void
    {
        if (DB::getDriverName() !== 'mysql') $this->markTestSkipped('MySQL 8 lock integration test.');

        $order = $this->draft();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders/'.$order->id.'/confirm', [])->assertOk();
        $order->items()->update(['production_stage' => 4]);
        $order->production_status = \Modules\Orders\Enums\OrderProductionStatus::READY_FOR_DELIVERY;
        $order->save();

        $primaryName = DB::getDefaultConnection();
        $primary = DB::connection($primaryName);
        $secondary = $this->secondConnection();
        $secondary->statement('SET innodb_lock_wait_timeout = 1');

        $primary->beginTransaction();
        try {
            $this->assertNotNull($primary->table('orders')->where('id', $order->id)
                ->lockForUpdate()->first());

            // Exercise the actual Orders-owned Delivery gateway over the second
            // independent database connection. It must wait for the order row lock.
            DB::setDefaultConnection('delivery_secondary');
            try {
                app(DeliveryOrderGateway::class)->lockEligible($order->id);
                $this->fail('Second delivery gateway unexpectedly bypassed the order lock.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('1205', $e->getMessage());
            } finally {
                DB::setDefaultConnection($primaryName);
            }
        } finally {
            $primary->rollBack();
            DB::disconnect('delivery_secondary');
        }

        // Once released, a new independent connection can use the gateway.
        DB::setDefaultConnection('delivery_secondary');
        try {
            $this->assertSame($order->id, app(DeliveryOrderGateway::class)
                ->lockEligible($order->id)['orderId']);
        } finally {
            DB::setDefaultConnection($primaryName);
            DB::disconnect('delivery_secondary');
        }
    }
}
