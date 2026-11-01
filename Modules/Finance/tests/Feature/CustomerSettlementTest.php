<?php

namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Str;
use Modules\Deliveries\Models\Delivery;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class CustomerSettlementTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'finance.ledger.view', 'finance.balances.view',
            'finance.opening_balances.create', 'finance.collections.create',
            'deliveries.create', 'deliveries.cancel',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function confirmed(string $amount, ?int $customerId = null): Order
    {
        $order = $this->draft([
            'customerId' => $customerId ?? $this->customer->id,
            'items' => [[
                'serviceId' => $this->service->id,
                'quantity' => 3,
                'specifications' => 'Two-sided',
                'agreedTotal' => $amount,
            ]],
        ]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->path($order).'/confirm', [])->assertOk();

        return $order->fresh();
    }

    private function deliver(Order $order, string $collectedAmount = '0.00'): Delivery
    {
        $order->items()->update(['production_stage' => 4]);
        $order->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $order->save();

        $body = ['collectedAmount' => $collectedAmount];
        if ($collectedAmount !== '0.00') $body['paymentMethod'] = 0;
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/deliveries/orders/'.$order->id.'/deliver', $body)->assertOk();

        return Delivery::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    private function url(string $from, string $to, ?int $customerId = null): string
    {
        return '/api/v1/finance/customers/'.($customerId ?? $this->customer->id)
            .'/settlement?dateFrom='.$from.'&dateTo='.$to;
    }

    public function test_delivered_only_statement_preserves_finance_debt_on_confirmation(): void
    {
        $today = now('Africa/Cairo')->toDateString();
        $delivered = $this->confirmed('350.00');
        $this->confirmed('500.00'); // Still in production; debt exists in Finance.
        $this->deliver($delivered);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id,
                'amount' => '50.00', 'paymentMethod' => 0,
            ])->assertCreated();

        $response = $this->getJson($this->url($today, $today))->assertOk()
            ->assertJsonPath('data.openingBalance', '0.00')
            ->assertJsonPath('data.deliveredTotal', '350.00')
            ->assertJsonPath('data.paymentsTotal', '50.00')
            ->assertJsonPath('data.closingBalance', '300.00')
            ->assertJsonPath('data.financeBalanceAsOf', '800.00')
            ->assertJsonPath('data.undeliveredDifference', '500.00')
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.kind', 'delivered_item')
            ->assertJsonPath('data.lines.0.quantity', 3)
            ->assertJsonPath('data.lines.0.signedAmount', '350.00')
            ->assertJsonPath('data.lines.1.kind', 'collection')
            ->assertJsonPath('data.lines.1.signedAmount', '-50.00');

        $this->assertDatabaseCount('finance_ledger_entries', 3);
        $this->assertDatabaseCount('deliveries', 1);

        // Customer isolation: the same period must not leak another customer's data.
        $this->getJson($this->url($today, $today, $this->secondCustomer->id))
            ->assertOk()->assertJsonPath('data.closingBalance', '0.00')
            ->assertJsonCount(0, 'data.lines');
    }

    public function test_prior_collection_rolls_into_opening_and_paid_delivery_is_not_double_counted(): void
    {
        $today = now('Africa/Cairo')->toDateString();
        $yesterday = now('Africa/Cairo')->subDay()->toDateString();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id,
                'amount' => '100.00', 'paymentMethod' => 0,
                'collectedOn' => $yesterday,
            ])->assertCreated();

        $this->deliver($this->confirmed('350.00'), '50.00');

        $this->getJson($this->url($today, $today))->assertOk()
            ->assertJsonPath('data.openingBalance', '-100.00')
            ->assertJsonPath('data.deliveredTotal', '350.00')
            ->assertJsonPath('data.paymentsTotal', '50.00')
            ->assertJsonPath('data.closingBalance', '200.00')
            ->assertJsonPath('data.financeBalanceAsOf', '200.00')
            ->assertJsonPath('data.undeliveredDifference', '0.00')
            ->assertJsonCount(2, 'data.lines');
    }

    public function test_delivery_cancellation_removes_work_without_mutating_confirmed_order_debt(): void
    {
        $today = now('Africa/Cairo')->toDateString();
        $delivery = $this->deliver($this->confirmed('350.00'));
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/deliveries/'.$delivery->id.'/cancel', [
                'reason' => 'Incorrect delivery',
            ])->assertOk();

        $this->getJson($this->url($today, $today))->assertOk()
            ->assertJsonPath('data.deliveredTotal', '0.00')
            ->assertJsonPath('data.closingBalance', '0.00')
            ->assertJsonPath('data.financeBalanceAsOf', '350.00')
            ->assertJsonPath('data.undeliveredDifference', '350.00')
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.1.kind', 'delivery_cancellation')
            ->assertJsonPath('data.lines.1.signedAmount', '-350.00');
        $this->assertDatabaseCount('finance_ledger_entries', 1);
    }

    public function test_rejects_invalid_dates_and_unauthorized_statement_reads(): void
    {
        $today = now('Africa/Cairo')->toDateString();
        $this->getJson($this->url('2026-13-01', $today))
            ->assertUnprocessable()->assertJsonValidationErrors('dateFrom');
        $this->getJson($this->url('2026-10-01', '2026-09-01'))
            ->assertUnprocessable()->assertJsonValidationErrors('dateTo');
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/settlement')
            ->assertUnprocessable()->assertJsonValidationErrors(['dateFrom', 'dateTo']);

        $this->adminRole->revokePermissionTo('finance.ledger.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson($this->url($today, $today))->assertForbidden();
    }
}
