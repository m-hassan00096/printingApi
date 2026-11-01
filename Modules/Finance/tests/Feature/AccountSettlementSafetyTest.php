<?php

namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Deliveries\Models\Delivery;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Services\LedgerService;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class AccountSettlementSafetyTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['finance.ledger.view', 'finance.collections.create', 'finance.collections.view', 'deliveries.create'] as $name) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function base(): string
    {
        return '/api/v1/finance/customers/'.$this->customer->id.'/settlements';
    }

    private function delivered(string $amount = '50.00'): Delivery
    {
        $order = $this->draft(['items' => [['serviceId' => $this->service->id, 'quantity' => 100, 'agreedTotal' => $amount]]]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($this->path($order).'/confirm', [])->assertOk();
        $order = $order->fresh();
        $order->items()->update(['production_stage' => 4]);
        $order->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $order->save();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/deliveries/orders/'.$order->id.'/deliver', ['collectedAmount' => '0.00'])->assertOk();
        return Delivery::query()->where('order_id', $order->id)->firstOrFail();
    }

    private function preview(): array
    {
        return $this->getJson($this->base().'/preview?dateTo='.now('Africa/Cairo')->toDateString())->assertOk()->json('data');
    }

    private function body(array $preview, string $amount): array
    {
        return ['dateTo' => $preview['dateTo'], 'previewHash' => $preview['previewHash'], 'amount' => $amount]
            + ($amount === '0.00' ? [] : ['paymentMethod' => 0]);
    }

    public function test_opening_adjustment_and_net_funds_explain_the_full_4950_balance(): void
    {
        $this->delivered();
        DB::transaction(function (): void {
            $ledger = app(LedgerService::class);
            $ledger->lockCustomer($this->customer->id);
            $ledger->post($this->customer->id, LedgerEntryType::OPENING_BALANCE, 500000, 'test-opening:'.$this->customer->id);
        });
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/finance/collections', [
            'customerId' => $this->customer->id, 'amount' => '100.00', 'paymentMethod' => 0,
        ])->assertCreated();
        $preview = $this->preview();
        $this->assertSame('5000.00', $preview['openingBalance']);
        $this->assertSame('50.00', $preview['deliveredTotal']);
        $this->assertSame('100.00', $preview['netPayments']);
        $this->assertSame('4950.00', $preview['dueNow']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($this->base(), $this->body($preview, '4950.00'))
            ->assertCreated()->assertJsonPath('data.balanceBefore', '4950.00')->assertJsonPath('data.balanceAfter', '0.00')
            ->assertJsonPath('data.accountBalanceAfter', '0.00');
    }

    public function test_cancellation_after_a_snapshot_requires_review_and_does_not_rewrite_cash(): void
    {
        $delivery = $this->delivered();
        $record = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->base(), $this->body($this->preview(), '50.00'))->assertCreated()->json('data');
        $snapshot = DB::table('finance_account_settlements')->where('id', $record['id'])->value('snapshot');
        // Isolate downstream evidence handling from the independently tested
        // delivery-cancellation workflow. No cash reversal is simulated here.
        DB::table('deliveries')->where('id', $delivery->id)->update(['cancelled_at' => now()]);
        $this->getJson($this->base().'/'.$record['id'])->assertOk()->assertJsonPath('data.status', 'needs_review');
        $this->assertSame($snapshot, DB::table('finance_account_settlements')->where('id', $record['id'])->value('snapshot'));
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertSame('0.00', $this->preview()['deliveredTotal']);
    }

    public function test_destination_failure_rolls_back_evidence_collection_and_ledger_together(): void
    {
        $this->delivered();
        $ledgerCount = DB::table('finance_ledger_entries')->count();
        $body = $this->body($this->preview(), '50.00');
        $body['paymentMethod'] = 1;
        $body['paymentDestinationId'] = 999999;
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($this->base(), $body)->assertUnprocessable();
        $this->assertDatabaseCount('finance_account_settlements', 0);
        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertSame($ledgerCount, DB::table('finance_ledger_entries')->count());
    }

    public function test_reused_key_with_another_amount_cannot_create_second_cash_posting(): void
    {
        $this->delivered('100.00');
        $key = (string) Str::uuid();
        $body = $this->body($this->preview(), '40.00');
        $this->withHeader('Idempotency-Key', $key)->postJson($this->base(), $body)->assertCreated();
        $body['amount'] = '50.00';
        $this->withHeader('Idempotency-Key', $key)->postJson($this->base(), $body)->assertStatus(409);
        $this->assertDatabaseCount('finance_account_settlements', 1);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertSame('60.00', $this->preview()['dueNow']);
    }

    public function test_empty_scope_and_future_cutoff_are_rejected_without_writes(): void
    {
        $preview = $this->preview();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($this->base(), $this->body($preview, '0.00'))
            ->assertUnprocessable();
        $this->getJson($this->base().'/preview?dateTo='.now('Africa/Cairo')->addDay()->toDateString())->assertUnprocessable();
        $this->assertDatabaseCount('finance_account_settlements', 0);
        $this->assertDatabaseCount('finance_collections', 0);
    }
}
