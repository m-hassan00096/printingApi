<?php
namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Str;
use Modules\Finance\Models\Collection;
use Modules\Finance\Models\LedgerEntry;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class FinanceApiTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'finance.balances.view', 'finance.ledger.view', 'finance.opening_balances.create',
            'finance.collections.view', 'finance.collections.create', 'finance.collections.reverse',
            'finance.refunds.view', 'finance.refunds.create',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_opening_debt_and_credit_are_exclusive_and_shown_as_integer_enum(): void
    {
        $id = $this->customer->id;
        $this->postJson("/api/v1/finance/customers/$id/opening-balance", [
            'amount' => '750.25', 'type' => 2,
        ])->assertCreated()->assertJsonPath('data', null);

        $this->getJson("/api/v1/finance/customers/$id/balance")->assertOk()
            ->assertJsonPath('data.amount', '750.25')->assertJsonPath('data.type', 2)
            ->assertJsonPath('data.typeLabel', __('finance.balance_types.credit'));

        $this->postJson("/api/v1/finance/customers/$id/opening-balance", [
            'amount' => '100.00', 'type' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseCount('finance_ledger_entries', 1);
    }

    public function test_cash_collection_overpayment_becomes_credit_automatically_and_is_idempotent(): void
    {
        $payload = ['customerId' => $this->customer->id, 'amount' => '4000.00', 'paymentMethod' => 0];
        $this->withHeader('Idempotency-Key', 'collect-a')
            ->postJson('/api/v1/finance/collections', $payload)
            ->assertCreated()->assertJsonPath('data.id', 1);

        $this->postJson('/api/v1/finance/collections', $payload)->assertCreated();
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseCount('finance_ledger_entries', 1);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '4000.00')->assertJsonPath('data.type', 2);
        $this->postJson('/api/v1/finance/collections', ['customerId' => $this->customer->id,
            'amount' => '4001.00', 'paymentMethod' => 0])->assertStatus(409);
    }

    public function test_reversing_collection_restores_debt_and_cannot_be_reversed_twice(): void
    {
        $this->withHeader('Idempotency-Key', 'collection-1')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '200.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->withHeader('Idempotency-Key', 'reverse-1')
            ->postJson('/api/v1/finance/collections/1/reverse', ['reason' => 'Incorrect entry'])
            ->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.type', 0)->assertJsonPath('data.amount', '0.00');
        $this->assertDatabaseCount('finance_ledger_entries', 2);
        $this->withHeader('Idempotency-Key', 'reverse-2')
            ->postJson('/api/v1/finance/collections/1/reverse', ['reason' => 'Another attempt'])
            ->assertStatus(409);
    }

    public function test_refund_is_limited_by_credit_and_requires_reason(): void
    {
        $this->withHeader('Idempotency-Key', 'col-2')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '100.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->withHeader('Idempotency-Key', 'refund-1')
            ->postJson('/api/v1/finance/refunds', [
                'customerId' => $this->customer->id, 'amount' => '101.00',
                'paymentMethod' => 0, 'reason' => 'Customer requested cash',
            ])->assertStatus(409);
        $this->postJson('/api/v1/finance/refunds', [
            'customerId' => $this->customer->id, 'amount' => '40.00',
            'paymentMethod' => 0, 'reason' => 'Customer requested cash',
        ])->assertCreated();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '60.00')->assertJsonPath('data.type', 2);
    }

    public function test_order_confirmation_and_cancellation_post_liability_without_refunding_deposit(): void
    {
        $order = $this->draft();
        $this->withHeader('Idempotency-Key', 'finance-confirm-1')
            ->postJson($this->path($order).'/confirm', [
                'depositAmount' => '400.00', 'paymentMethod' => 0,
            ])->assertOk()->assertJsonPath('data', null);
        $order->refresh();
        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseHas('finance_ledger_entries', ['customer_id' => $this->customer->id,
            'event_key' => 'order-confirm:'.$order->id, 'signed_amount' => '350.00']);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '50.00')->assertJsonPath('data.type', 2);

        $this->withHeader('Idempotency-Key', 'finance-cancel-1')
            ->postJson($this->path($order).'/cancel', ['reason' => 'Customer changed mind'])
            ->assertOk();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '400.00')->assertJsonPath('data.type', 2);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseCount('finance_ledger_entries', 3);
    }

    public function test_refund_without_credit_returns_conflict_and_does_not_post(): void
    {
        $this->withHeader('Idempotency-Key', 'refund-zero')
            ->postJson('/api/v1/finance/refunds', [
                'customerId' => $this->customer->id, 'amount' => '10.00',
                'paymentMethod' => 0, 'reason' => 'Return funds',
            ])->assertStatus(409);
        $this->assertDatabaseCount('finance_refunds', 0);
        $this->assertDatabaseCount('finance_ledger_entries', 0);
    }

    public function test_collection_requires_destination_for_transfer_and_wallet(): void
    {
        $this->withHeader('Idempotency-Key', 'missing-destination')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '10.00',
                'paymentMethod' => 1,
            ])->assertUnprocessable()->assertJsonValidationErrors('paymentDestinationId');
    }
}
