<?php

namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Deliveries\Models\Delivery;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class AccountSettlementClosingTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['finance.ledger.view', 'finance.balances.view', 'finance.collections.create',
            'finance.collections.reverse', 'finance.collections.view', 'finance.opening_balances.create',
            'deliveries.create', 'deliveries.cancel'] as $name) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function confirmed(string $amount, string $deposit = '0.00'): Order
    {
        $order = $this->draft(['items' => [[
            'serviceId' => $this->service->id, 'quantity' => 10, 'agreedTotal' => $amount,
        ]]]);
        $data = $deposit === '0.00' ? [] : ['depositAmount' => $deposit, 'paymentMethod' => 0];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->path($order).'/confirm', $data)->assertOk();
        return $order->fresh();
    }

    private function delivered(Order $order, string $amount = '0.00'): Delivery
    {
        $order->items()->update(['production_stage' => 4]);
        $order->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $order->save();
        $data = ['collectedAmount' => $amount];
        if ($amount !== '0.00') $data['paymentMethod'] = 0;
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/deliveries/orders/'.$order->id.'/deliver', $data)->assertOk();
        return Delivery::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    private function base(?int $customer = null): string
    {
        return '/api/v1/finance/customers/'.($customer ?? $this->customer->id).'/settlements';
    }

    private function preview(?string $to = null): array
    {
        return $this->getJson($this->base().'/preview?dateTo='.($to ?? now('Africa/Cairo')->toDateString()))
            ->assertOk()->json('data');
    }

    private function payload(array $preview, string $amount): array
    {
        return ['dateTo' => $preview['dateTo'], 'previewHash' => $preview['previewHash'], 'amount' => $amount]
            + ($amount === '0.00' ? [] : ['paymentMethod' => 0]);
    }

    public function test_deposit_delivery_payment_and_settlement_are_counted_once(): void
    {
        $this->delivered($this->confirmed('1000.00', '200.00'), '500.00');
        $preview = $this->preview();
        $this->assertSame('700.00', $preview['netPayments']);
        $this->assertSame('300.00', $preview['dueNow']);
        $key = (string) Str::uuid();
        $body = $this->payload($preview, '300.00');
        $saved = $this->withHeader('Idempotency-Key', $key)->postJson($this->base(), $body)
            ->assertCreated()->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.snapshot.after.dueNow', '0.00')->json('data');
        $this->assertNotNull($saved['collectionId']);
        $this->assertSame(1, DB::table('finance_ledger_entries')->where('type', 1)->count());
        $this->assertSame(3, DB::table('finance_collections')->count());
        $this->assertDatabaseCount('finance_account_settlements', 1);

        $this->withHeader('Idempotency-Key', $key)->postJson($this->base(), $body)
            ->assertCreated()->assertJsonPath('data.id', $saved['id']);
        $this->assertDatabaseCount('finance_collections', 3);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($this->base(), $body)->assertStatus(409);
        $this->assertDatabaseCount('finance_collections', 3);

        $current = $this->preview();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($current, '0.00'))
            ->assertCreated()->assertJsonPath('data.id', $saved['id']);
        $this->assertDatabaseCount('finance_account_settlements', 1);
    }

    public function test_partial_settlement_keeps_real_remaining_balance_and_pending_order_debt(): void
    {
        $this->delivered($this->confirmed('1000.00'));
        $this->confirmed('2000.00');
        $preview = $this->preview();
        $this->assertSame('2000.00', $preview['outsideBalance']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '400.00'))
            ->assertCreated()->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.snapshot.after.balanceBefore', '600.00')
            ->assertJsonPath('data.snapshot.after.accountBalance', '2600.00');
        $current = $this->preview();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($current, '600.00'))
            ->assertCreated()->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.snapshot.after.accountBalance', '2000.00');
        $this->assertDatabaseCount('finance_collections', 2);
    }

    public function test_external_payment_invalidates_preview_without_posting_another_collection(): void
    {
        $this->delivered($this->confirmed('1000.00'));
        $preview = $this->preview();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/finance/collections', [
            'customerId' => $this->customer->id, 'amount' => '250.00', 'paymentMethod' => 0,
        ])->assertCreated();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '1000.00'))->assertStatus(409);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseCount('finance_account_settlements', 0);
        $this->assertSame('750.00', $this->preview()['dueNow']);
    }

    public function test_already_paid_scope_records_evidence_without_creating_cash_or_debt(): void
    {
        $this->delivered($this->confirmed('1000.00'), '1000.00');
        $beforeCount = DB::table('finance_ledger_entries')->count();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($this->preview(), '0.00'))
            ->assertCreated()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.collectionId', null);
        $this->assertSame($beforeCount, DB::table('finance_ledger_entries')->count());
        $this->assertDatabaseCount('finance_collections', 1);
    }

    public function test_unpaid_scope_can_record_zero_cash_evidence_but_cannot_overcollect(): void
    {
        $this->delivered($this->confirmed('1000.00'));
        $preview = $this->preview();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '1000.01'))
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '0.00'))
            ->assertCreated()->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.balanceAfter', '1000.00')
            ->assertJsonPath('data.amountCollected', '0.00')
            ->assertJsonPath('data.collectionId', null)->json('data');
        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertDatabaseCount('finance_account_settlements', 1);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($this->preview(), '0.00'))
            ->assertCreated()->assertJsonPath('data.id', $saved['id']);
        $this->assertDatabaseCount('finance_account_settlements', 1);
    }

    public function test_past_work_cutoff_still_counts_payments_received_today(): void
    {
        $delivery = $this->delivered($this->confirmed('1000.00'));
        $yesterday = now('Africa/Cairo')->subDay()->toDateString();
        DB::table('deliveries')->where('id', $delivery->id)->update(['delivered_local_date' => $yesterday]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/finance/collections', [
            'customerId' => $this->customer->id, 'amount' => '700.00', 'paymentMethod' => 0,
        ])->assertCreated();
        $preview = $this->preview($yesterday);
        $this->assertSame('300.00', $preview['dueNow']);
        $this->assertSame(now('Africa/Cairo')->toDateString(), $preview['paymentsThrough']);
        $record = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '300.00'))->assertCreated()->json('data');
        $this->assertDatabaseHas('finance_collections', ['id' => $record['collectionId'],
            'collected_on' => now('Africa/Cairo')->toDateString()]);
    }

    public function test_reversal_flags_original_evidence_without_rewriting_its_snapshot(): void
    {
        $this->delivered($this->confirmed('1000.00'));
        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($this->preview(), '1000.00'))->assertCreated()->json('data');
        $original = DB::table('finance_account_settlements')->where('id', $saved['id'])->value('snapshot');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/finance/collections/'.$saved['collectionId'].'/reverse', [
                'reason' => 'Payment was registered incorrectly',
            ])->assertOk();
        $this->getJson($this->base().'/'.$saved['id'])->assertOk()
            ->assertJsonPath('data.status', 'needs_review')->assertJsonPath('data.originalStatus', 'closed');
        $this->assertSame($original, DB::table('finance_account_settlements')->where('id', $saved['id'])->value('snapshot'));
        $this->assertSame('1000.00', $this->preview()['dueNow']);
    }

    public function test_advance_references_are_disclosed_without_double_deduction_or_refund(): void
    {
        $this->delivered($this->confirmed('100.00'));
        $this->confirmed('1000.00', '200.00');
        $preview = $this->preview();
        $this->assertSame('200.00', $preview['advancesForOtherWork']);
        $this->assertSame('200.00', $preview['netPayments']);
        $this->assertSame('100.00', $preview['creditInScope']);
        $this->assertSame('900.00', $preview['accountBalance']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($preview, '0.00'))
            ->assertCreated()->assertJsonPath('data.status', 'credit');
        $this->assertDatabaseCount('finance_refunds', 0);
        $this->assertDatabaseCount('finance_collections', 1);
    }

    public function test_customer_isolation_and_confirmation_permissions(): void
    {
        $this->delivered($this->confirmed('100.00'), '100.00');
        $saved = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($this->preview(), '0.00'))->assertCreated()->json('data');
        $this->getJson($this->base($this->secondCustomer->id).'/'.$saved['id'])->assertNotFound();
        $this->getJson($this->base($this->secondCustomer->id))->assertOk()->assertJsonCount(0, 'data.settlements');
        $this->adminRole->revokePermissionTo('finance.collections.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->payload($this->preview(), '0.00'))->assertForbidden();
    }
}
