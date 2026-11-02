<?php

namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Str;
use Modules\Deliveries\Models\Delivery;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class CustomerSettlementRecordsTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'finance.ledger.view', 'finance.settlements.view',
            'finance.settlements.create', 'finance.settlements.discount',
            'finance.collections.create', 'deliveries.create',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function delivered(string $amount = '350.00'): Order
    {
        $order = $this->draft([
            'items' => [[
                'serviceId' => $this->service->id,
                'quantity' => 3, 'specifications' => 'Double-sided',
                'agreedTotal' => $amount,
            ]],
        ]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->path($order).'/confirm', [])->assertOk();

        $order->items()->update(['production_stage' => 4]);
        $order->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $order->save();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/deliveries/orders/'.$order->id.'/deliver', [
                'collectedAmount' => '0.00',
            ])->assertOk();

        $this->assertDatabaseHas('deliveries', ['order_id' => $order->id]);

        return $order;
    }

    private function root(?int $customerId = null): string
    {
        return '/api/v1/finance/customers/'.($customerId ?? $this->customer->id).'/settlement-records';
    }

    private function settlement(array $changes = []): array
    {
        $today = now('Africa/Cairo')->toDateString();
        return array_replace([
            'dateFrom' => $today, 'dateTo' => $today,
            'discountAmount' => '0.00', 'collectedAmount' => '0.00',
        ], $changes);
    }

    public function test_discount_and_collection_are_atomic_and_distinct_ledger_movements(): void
    {
        $today = now('Africa/Cairo')->toDateString();
        $this->delivered();

        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'discountAmount' => '100.00',
                'discountReason' => 'Agreed compensation',
                'collectedAmount' => '50.00',
                'paymentMethod' => 0,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.discountAmount', '100.00')
            ->assertJsonPath('data.collectedAmount', '50.00')
            ->assertJsonPath('data.balanceBefore', '350.00')
            ->assertJsonPath('data.balanceAfter', '200.00')
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.createdByName', 'Orders Admin')
            ->assertJsonPath('data.statement.deliveredTotal', '350.00')
            ->assertJsonPath('data.statement.paymentsTotal', '50.00')
            ->assertJsonPath('data.statement.discountsTotal', '100.00')
            ->assertJsonPath('data.statement.closingBalance', '200.00');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('finance_customer_settlements', [
            'id' => $id, 'customer_id' => $this->customer->id,
            'settled_on' => $today,
            'discount_amount' => '100.00', 'collected_amount' => '50.00',
        ]);
        $this->assertDatabaseHas('finance_ledger_entries', [
            'customer_id' => $this->customer->id,
            'type' => LedgerEntryType::SETTLEMENT_DISCOUNT->value,
            'signed_amount' => '-100.00',
            'effective_date' => $today,
        ]);
        $this->assertDatabaseHas('finance_collections', [
            'customer_id' => $this->customer->id,
            'amount' => '50.00',
        ]);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertForbidden(); // No balance-view permission is granted.
        $this->getJson($this->root())->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.settlements.0.balanceAfter', '200.00');
        $this->getJson($this->root().'/'.$id)->assertOk()
            ->assertJsonPath('data.statement.lines.1.kind', 'settlement_discount')
            ->assertJsonPath('data.statement.lines.2.kind', 'collection');
        $this->getJson($this->root($this->secondCustomer->id).'/'.$id)->assertNotFound();
    }

    public function test_discount_only_preserves_a_historical_snapshot_after_more_collections(): void
    {
        $this->delivered('300.00');
        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'discountAmount' => '120.00', 'discountReason' => 'Customer goodwill',
            ]))->assertCreated()->assertJsonPath('data.statement.closingBalance', '180.00')
            ->assertJsonPath('data.statement.paymentsTotal', '0.00')->json('data');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '70.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->getJson($this->root().'/'.$saved['id'])->assertOk()
            ->assertJsonPath('data.statement.closingBalance', '180.00')
            ->assertJsonPath('data.statement.discountsTotal', '120.00');
        $today = now('Africa/Cairo')->toDateString();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id
            .'/settlement?dateFrom='.$today.'&dateTo='.$today)
            ->assertOk()->assertJsonPath('data.closingBalance', '110.00')
            ->assertJsonPath('data.paymentsTotal', '70.00');
    }

    public function test_rejects_over_discount_overlap_and_unauthorized_discount_without_posting(): void
    {
        $this->delivered('200.00');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'discountAmount' => '201.00', 'discountReason' => 'Too much',
            ]))->assertUnprocessable();
        $this->assertDatabaseCount('finance_customer_settlements', 0);
        $this->assertDatabaseCount('finance_collections', 0);

        $this->adminRole->revokePermissionTo('finance.settlements.discount');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'discountAmount' => '20.00', 'discountReason' => 'No permission',
            ]))->assertForbidden();
        $this->assertDatabaseCount('finance_customer_settlements', 0);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement())->assertCreated();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement())->assertUnprocessable();
        $this->assertDatabaseCount('finance_customer_settlements', 1);

        $this->adminRole->revokePermissionTo('finance.settlements.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement())->assertForbidden();
    }

    public function test_same_idempotency_key_returns_same_record_without_second_discount_or_receipt(): void
    {
        $this->delivered('250.00');
        $payload = $this->settlement([
            'discountAmount' => '20.00', 'discountReason' => 'Minor adjustment',
            'collectedAmount' => '30.00', 'paymentMethod' => 0,
        ]);
        $key = (string) Str::uuid();
        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson($this->root(), $payload)->assertCreated();
        $second = $this->withHeader('Idempotency-Key', $key)
            ->postJson($this->root(), $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('finance_customer_settlements', 1);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('finance_ledger_entries')
            ->where('type', LedgerEntryType::SETTLEMENT_DISCOUNT->value)->count());
    }


    public function test_past_work_period_posts_discount_and_receipt_on_the_actual_day(): void
    {
        $today = now('Africa/Cairo')->toDateString();
        $yesterday = now('Africa/Cairo')->subDay()->toDateString();
        $order = $this->delivered('350.00');
        \Illuminate\Support\Facades\DB::table('deliveries')
            ->where('order_id', $order->id)->update(['delivered_local_date' => $yesterday]);

        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'dateFrom' => $yesterday, 'dateTo' => $yesterday,
                'discountAmount' => '100.00', 'discountReason' => 'Old-work settlement',
                'collectedAmount' => '50.00', 'paymentMethod' => 0,
            ]))->assertCreated()
            ->assertJsonPath('data.settledOn', $today)
            ->assertJsonPath('data.dateTo', $yesterday)
            ->assertJsonPath('data.statement.closingBalance', '200.00')
            ->assertJsonPath('data.statement.lines.1.date', $today)
            ->assertJsonPath('data.statement.lines.2.date', $today)->json('data');

        $this->assertDatabaseHas('finance_ledger_entries', [
            'type' => LedgerEntryType::SETTLEMENT_DISCOUNT->value,
            'effective_date' => $today,
            'signed_amount' => '-100.00',
        ]);
        $this->assertDatabaseHas('finance_collections', [
            'id' => $saved['collectionId'],
            'collected_on' => $today, 'amount' => '50.00',
        ]);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id
            .'/settlement?dateFrom='.$yesterday.'&dateTo='.$yesterday)
            ->assertOk()->assertJsonPath('data.closingBalance', '350.00');
        $this->getJson($this->root().'/'.$saved['id'])->assertOk()
            ->assertJsonPath('data.statement.closingBalance', '200.00');

        // The next period inherits real-day movements without losing historic work.
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id
            .'/settlement?dateFrom='.$today.'&dateTo='.$today)
            ->assertOk()->assertJsonPath('data.openingBalance', '350.00')
            ->assertJsonPath('data.discountsTotal', '100.00')
            ->assertJsonPath('data.paymentsTotal', '50.00')
            ->assertJsonPath('data.closingBalance', '200.00');
    }

    public function test_settlement_only_view_role_can_read_without_full_finance_ledger_permission(): void
    {
        $this->adminRole->revokePermissionTo('finance.ledger.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $today = now('Africa/Cairo')->toDateString();

        $this->getJson('/api/v1/finance/customers/'.$this->customer->id
            .'/settlement?dateFrom='.$today.'&dateTo='.$today)->assertOk();
        $this->getJson($this->root())->assertOk();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/ledger')->assertForbidden();
    }

    public function test_cannot_record_a_collection_without_collection_create_permission(): void
    {
        $this->delivered('120.00');
        $this->adminRole->revokePermissionTo('finance.collections.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'collectedAmount' => '30.00', 'paymentMethod' => 0,
            ]))->assertForbidden();
        $this->assertDatabaseCount('finance_customer_settlements', 0);
        $this->assertDatabaseCount('finance_collections', 0);
    }



    public function test_reversed_receipt_is_flagged_without_rewriting_issued_statement(): void
    {
        $this->adminRole->givePermissionTo(
            Permission::findOrCreate('finance.collections.reverse', 'web')
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->delivered('250.00');

        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'collectedAmount' => '50.00', 'paymentMethod' => 0,
            ]))->assertCreated()->assertJsonPath('data.collectionReversed', false)->json('data');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/finance/collections/'.$saved['collectionId'].'/reverse', [
                'reason' => 'Receipt entered by mistake',
            ])->assertOk();

        $this->getJson($this->root().'/'.$saved['id'])->assertOk()
            ->assertJsonPath('data.collectionReversed', true)
            ->assertJsonPath('data.statement.closingBalance', '200.00');
        $this->getJson($this->root())->assertOk()
            ->assertJsonPath('data.settlements.0.collectionReversed', true);
        $today = now('Africa/Cairo')->toDateString();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id
            .'/settlement?dateFrom='.$today.'&dateTo='.$today)
            ->assertOk()->assertJsonPath('data.closingBalance', '250.00');
    }


    public function test_credit_balance_can_be_saved_without_discount_or_collection(): void
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id,
                'amount' => '40.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement())->assertCreated()
            ->assertJsonPath('data.balanceBefore', '-40.00')
            ->assertJsonPath('data.balanceAfter', '-40.00')
            ->assertJsonPath('data.status', 'credit');
        $this->assertDatabaseCount('finance_collections', 1);
    }

    public function test_requires_discount_reason_and_valid_payment_details(): void
    {
        $this->delivered('250.00');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'discountAmount' => '20.00',
            ]))->assertUnprocessable()->assertJsonValidationErrors('discountReason');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'collectedAmount' => '50.00',
            ]))->assertUnprocessable()->assertJsonValidationErrors('paymentMethod');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->root(), $this->settlement([
                'collectedAmount' => '50.00', 'paymentMethod' => 1,
            ]))->assertUnprocessable()->assertJsonValidationErrors('paymentDestinationId');

        $this->assertDatabaseCount('finance_customer_settlements', 0);
        $this->assertDatabaseCount('finance_collections', 0);
    }
}
