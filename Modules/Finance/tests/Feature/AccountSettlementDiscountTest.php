<?php

namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Deliveries\Models\Delivery;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class AccountSettlementDiscountTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'finance.ledger.view', 'finance.balances.view', 'finance.collections.create',
            'finance.settlements.discount', 'finance.collections.reverse', 'deliveries.create',
        ] as $name) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function base(?int $customerId = null): string
    {
        return '/api/v1/finance/customers/'.($customerId ?? $this->customer->id).'/settlements';
    }

    private function deliver(string $total = '2500.00'): Delivery
    {
        $order = $this->draft(['items' => [[
            'serviceId' => $this->service->id, 'quantity' => 10, 'agreedTotal' => $total,
        ]]]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->path($order).'/confirm', [])->assertOk();
        $order->items()->update(['production_stage' => 4]);
        $order->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $order->save();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/deliveries/orders/'.$order->id.'/deliver',
                ['collectedAmount' => '0.00'])->assertOk();

        return Delivery::query()->where('order_id', $order->id)->firstOrFail();
    }

    private function preview(?string $date = null): array
    {
        return $this->getJson($this->base().'/preview?dateTo='.
            ($date ?? now('Africa/Cairo')->toDateString()))->assertOk()->json('data');
    }

    private function payload(array $preview, string $discount, string $amount, array $extra = []): array
    {
        return array_merge([
            'dateTo' => $preview['dateTo'], 'previewHash' => $preview['previewHash'],
            'discountAmount' => $discount, 'discountReason' => 'Agreed customer discount',
            'amount' => $amount,
        ], $amount === '0.00' ? [] : ['paymentMethod' => 0], $extra);
    }

    public function test_fixed_discount_and_actual_cash_are_two_atomic_financial_movements(): void
    {
        $this->deliver();
        $preview = $this->preview();
        $this->assertSame('2500.00', $preview['dueNow']);
        $this->assertSame('0.00', $preview['discountsTotal']);

        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '1000.00', '500.00'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.discountAmount', '1000.00')
            ->assertJsonPath('data.amountCollected', '500.00')
            ->assertJsonPath('data.balanceBefore', '2500.00')
            ->assertJsonPath('data.balanceAfter', '1000.00')
            ->assertJsonPath('data.accountBalanceAfter', '1000.00')
            ->assertJsonPath('data.snapshot.after.discountsTotal', '1000.00')
            ->assertJsonPath('data.snapshot.after.netPayments', '500.00')->json('data');

        $this->assertNotNull($saved['collectionId']);
        $this->assertNotNull($saved['discountLedgerEntryId']);
        $this->assertDatabaseHas('finance_account_settlements', [
            'id' => $saved['id'], 'discount_amount' => '1000.00',
            'collected_amount' => '500.00',
            'discount_ledger_entry_id' => $saved['discountLedgerEntryId'],
        ]);
        $this->assertDatabaseHas('finance_ledger_entries', [
            'id' => $saved['discountLedgerEntryId'],
            'type' => LedgerEntryType::SETTLEMENT_DISCOUNT->value,
            'signed_amount' => '-1000.00', 'effective_date' => now('Africa/Cairo')->toDateString(),
        ]);
        $this->assertDatabaseHas('finance_collections', [
            'id' => $saved['collectionId'], 'amount' => '500.00',
        ]);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertOk()->assertJsonPath('data.amount', '1000.00')->assertJsonPath('data.type', 1);
        $this->getJson($this->base().'/'.$saved['id'])->assertOk()
            ->assertJsonPath('data.discountReason', 'Agreed customer discount');
        $this->getJson($this->base($this->secondCustomer->id).'/'.$saved['id'])->assertNotFound();
    }

    public function test_discount_only_is_valid_without_registering_cash_or_making_credit(): void
    {
        $this->deliver('1500.00');
        $preview = $this->preview();
        $key = (string) Str::uuid();
        $payload = $this->payload($preview, '1000.00', '0.00');

        $saved = $this->withHeader('Idempotency-Key', $key)
            ->postJson($this->base(), $payload)->assertCreated()
            ->assertJsonPath('data.balanceAfter', '500.00')
            ->assertJsonPath('data.amountCollected', '0.00')
            ->assertJsonPath('data.collectionId', null)
            ->assertJsonPath('data.status', 'partial')->json('data');

        $this->assertDatabaseCount('finance_collections', 0);
        $this->withHeader('Idempotency-Key', $key)
            ->postJson($this->base(), $payload)->assertCreated()->assertJsonPath('data.id', $saved['id']);
        $this->assertDatabaseCount('finance_account_settlements', 1);
        $this->assertSame('500.00', $this->preview()['dueNow']);
        $this->assertSame('0.00', $this->preview()['netPayments']);
        $this->assertSame('1000.00', $this->preview()['discountsTotal']);
    }

    public function test_full_discount_does_not_generate_a_phantom_receipt(): void
    {
        $this->deliver('100.00');
        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($this->preview(), '100.00', '0.00'))
            ->assertCreated()->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.balanceAfter', '0.00')->json('data');

        $this->assertNull($saved['receiptNumber']);
        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertDatabaseCount('finance_account_settlements', 1);
        $this->assertSame('0.00', $this->preview()['dueNow']);
    }

    public function test_invalid_discount_reason_or_amount_is_rejected_without_ledger_posts(): void
    {
        $this->deliver('500.00');
        $p = $this->preview();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($p, '501.00', '0.00'))
            ->assertUnprocessable()->assertJsonValidationErrors('discountAmount');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($p, '100.00', '0.00', [
                'discountReason' => '',
            ]))->assertUnprocessable()->assertJsonValidationErrors('discountReason');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($p, '100.00', '401.00'))
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($p, '100.00', '50.00', [
                'paymentMethod' => 1, 'paymentDestinationId' => 9999999,
            ]))->assertUnprocessable();

        $this->assertDatabaseCount('finance_account_settlements', 0);
        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertSame(0, DB::table('finance_ledger_entries')
            ->where('type', LedgerEntryType::SETTLEMENT_DISCOUNT->value)->count());
    }

    public function test_discount_needs_independent_permission_even_if_employee_can_collect(): void
    {
        $this->deliver('300.00');
        $preview = $this->preview();
        $this->adminRole->revokePermissionTo('finance.settlements.discount');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '100.00', '0.00'))->assertForbidden();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '0.00', '50.00'))
            ->assertCreated()->assertJsonPath('data.discountAmount', '0.00')
            ->assertJsonPath('data.amountCollected', '50.00');
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertSame(0, DB::table('finance_ledger_entries')
            ->where('type', LedgerEntryType::SETTLEMENT_DISCOUNT->value)->count());
    }

    public function test_old_work_cutoff_records_the_discount_and_receipt_on_the_real_day(): void
    {
        $delivery = $this->deliver('500.00');
        $yesterday = now('Africa/Cairo')->subDay()->toDateString();
        DB::table('deliveries')->where('id', $delivery->id)
            ->update(['delivered_local_date' => $yesterday]);

        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($this->preview($yesterday), '100.00', '50.00'))
            ->assertCreated()->assertJsonPath('data.dateTo', $yesterday)
            ->assertJsonPath('data.settledOn', now('Africa/Cairo')->toDateString())->json('data');

        $this->assertDatabaseHas('finance_ledger_entries', [
            'id' => $saved['discountLedgerEntryId'],
            'effective_date' => now('Africa/Cairo')->toDateString(),
        ]);
        $this->assertDatabaseHas('finance_collections', [
            'id' => $saved['collectionId'], 'collected_on' => now('Africa/Cairo')->toDateString(),
        ]);
        $report = $this->getJson('/api/v1/finance/customers/'.$this->customer->id
            .'/settlement?dateFrom='.$yesterday.'&dateTo='.$yesterday)->assertOk();
        $report->assertJsonPath('data.discountsTotal', '0.00');
        $this->assertSame('100.00', $this->preview($yesterday)['discountsTotal']);
    }

    public function test_new_cash_movement_invalidates_discount_preview_atomically(): void
    {
        $this->deliver('600.00');
        $preview = $this->preview();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id,
                'amount' => '80.00', 'paymentMethod' => 0,
            ])->assertCreated();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '200.00', '100.00'))
            ->assertStatus(409);
        $this->assertDatabaseCount('finance_account_settlements', 0);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertSame(0, DB::table('finance_ledger_entries')
            ->where('type', LedgerEntryType::SETTLEMENT_DISCOUNT->value)->count());
    }
}
