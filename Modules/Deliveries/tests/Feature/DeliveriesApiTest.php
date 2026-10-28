<?php
namespace Modules\Deliveries\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Str;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Delivery;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Models\Collection;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class DeliveriesApiTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'deliveries.ready.view', 'deliveries.list', 'deliveries.view',
            'deliveries.create', 'deliveries.cancel',
            'finance.balances.view', 'finance.collections.create', 'finance.collections.view',
            'finance.collections.reverse',
        ] as $perm) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($perm, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function confirmed(array $changes = []): Order
    {
        $o = $this->draft($changes);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->path($o).'/confirm', [])->assertOk();
        return $o->fresh();
    }

    private function ready(array $changes = []): Order
    {
        $o = $this->confirmed($changes);
        $o->items()->update(['production_stage' => 4]);
        $o->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $o->save();
        return $o->fresh();
    }

    private function deliver(Order $order, array $body = [], string $key = 'deliver-one'): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/deliveries/orders/'.$order->id.'/deliver', $body);
    }

    private function cancel(int $id, array $body = ['reason' => 'Delivered to wrong person'], string $key = 'cancel-one'): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/deliveries/'.$id.'/cancel', $body);
    }

    public function test_ready_board_exposes_only_confirmed_orders_with_ready_items_and_preview(): void
    {
        $fully = $this->ready();
        $partial = $this->confirmed(['items' => [
            ['serviceId' => $this->service->id, 'quantity' => 100, 'agreedTotal' => '100.00'],
            ['serviceId' => $this->secondService->id, 'quantity' => 100, 'agreedTotal' => '200.00'],
        ]]);
        $partial->items()->orderBy('id')->firstOrFail()->forceFill(['production_stage' => 4])->save();
        $partial->production_status = OrderProductionStatus::PARTIALLY_READY;
        $partial->save();
        $unready = $this->confirmed();
        $draft = $this->draft();

        $this->getJson('/api/v1/deliveries/ready')->assertOk()
            ->assertJsonPath('data.pagination.total', 2);
        $this->getJson('/api/v1/deliveries/ready/'.$fully->id)->assertOk()
            ->assertJsonPath('data.order.isReadyForDelivery', true)
            ->assertJsonPath('data.canDeliver', true)->assertJsonPath('data.canCollect', true)
            ->assertJsonPath('data.customerBalance.type', 1);
        $this->getJson('/api/v1/deliveries/ready/'.$partial->id)->assertOk()
            ->assertJsonPath('data.canDeliver', false)
            ->assertJsonPath('data.order.readyItemsCount', 1);
        $this->getJson('/api/v1/deliveries/ready/'.$unready->id)->assertNotFound();
        $this->getJson('/api/v1/deliveries/ready/'.$draft->id)->assertNotFound();
    }

    public function test_delivery_customer_select_and_ready_history_filters_use_customer_id(): void
    {
        $ready = $this->ready();
        $otherCustomer = $this->secondCustomer;
        $other = $this->ready(['customerId' => $otherCustomer->id]);

        $this->getJson('/api/v1/deliveries/ready?filter[customerId]='.$ready->customer_id)
            ->assertOk()->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.orders.0.orderId', $ready->id);
        $this->getJson('/api/v1/deliveries/ready?filter[customerId]='.$otherCustomer->id)
            ->assertOk()->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.orders.0.orderId', $other->id);

        $this->getJson('/api/v1/selects?selects=deliveryCustomers&params[deliveryCustomers]=ready')
            ->assertOk()->assertJsonCount(2, 'data.0.options');
        $this->getJson('/api/v1/selects?selects=deliveryCustomers&params[deliveryCustomers]=history')
            ->assertOk()->assertJsonCount(0, 'data.0.options');

        $this->deliver($ready)->assertOk();
        $this->getJson('/api/v1/deliveries?filter[customerId]='.$ready->customer_id)
            ->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/v1/deliveries?filter[customerId]='.$otherCustomer->id)
            ->assertOk()->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/deliveries?filter[customerId]=bad')
            ->assertUnprocessable()->assertJsonValidationErrors('filter.customerId');
        $this->getJson('/api/v1/selects?selects=deliveryCustomers&params[deliveryCustomers]=ready')
            ->assertOk()->assertJsonCount(1, 'data.0.options');
        $this->getJson('/api/v1/selects?selects=deliveryCustomers&params[deliveryCustomers]=history')
            ->assertOk()->assertJsonCount(1, 'data.0.options');
        $this->getJson('/api/v1/selects?selects=deliveryCustomers&params[deliveryCustomers]=invalid')
            ->assertUnprocessable();
    }

    public function test_delivery_customer_options_respect_tab_permissions(): void
    {
        $this->ready();
        $this->adminRole->revokePermissionTo('deliveries.ready.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson('/api/v1/selects?selects=deliveryCustomers&params[deliveryCustomers]=ready')
            ->assertForbidden();
        $this->getJson('/api/v1/selects?selects=deliveryCustomers&params[deliveryCustomers]=history')
            ->assertOk()->assertJsonCount(0, 'data.0.options');
    }

    public function test_zero_collection_delivery_is_atomic_idempotent_and_prevents_production_changes(): void
    {
        $o = $this->ready();
        $this->deliver($o, ['receiverName' => '  Warehouse staff  ', 'collectedAmount' => '0.00'])
            ->assertOk()->assertJsonPath('data', null);
        $this->deliver($o, ['receiverName' => 'Warehouse staff', 'collectedAmount' => '0.00'])
            ->assertOk();
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertDatabaseCount('finance_ledger_entries', 1); // confirmation liability only
        $d = Delivery::query()->firstOrFail();
        $this->assertSame('Warehouse staff', $d->receiver_name);
        $this->assertNull($d->collection_id);
        $this->assertSame(OrderProductionStatus::DELIVERED, $o->fresh()->production_status);
        $this->assertNotNull($o->fresh()->delivered_at);
        $this->getJson('/api/v1/deliveries/ready')->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/deliveries/'.$d->id)->assertOk()
            ->assertJsonPath('data.delivery.receivedAmount', '0.00');
        $this->deliver($o, [], 'another-key')->assertStatus(409);
    }

    public function test_payment_on_delivery_creates_one_delivery_collection_and_ledger_entry(): void
    {
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '100.00', 'paymentMethod' => 0], 'paid-one')->assertOk();
        $this->assertDatabaseHas('finance_collections', ['order_id' => $o->id,
            'source' => CollectionSource::DELIVERY->value, 'status' => CollectionStatus::POSTED->value,
            'amount' => '100.00']);
        $this->assertDatabaseCount('finance_ledger_entries', 2);
        $d = Delivery::query()->firstOrFail();
        $this->assertNotNull($d->collection_id);
        $this->getJson('/api/v1/deliveries/'.$d->id)->assertOk()
            ->assertJsonPath('data.delivery.collection.source', 2)
            ->assertJsonPath('data.delivery.receivedAmount', '100.00')
            ->assertJsonPath('data.delivery.effectiveReceivedAmount', '100.00');
    }

    public function test_partial_order_rejects_delivery_without_collection(): void
    {
        $o = $this->confirmed();
        $this->deliver($o)->assertStatus(409);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertDatabaseCount('finance_ledger_entries', 1);
    }

    public function test_credit_requires_explicit_confirmation_before_paid_delivery(): void
    {
        $o = $this->ready();
        $payload = ['collectedAmount' => '351.00', 'paymentMethod' => 0];
        $this->deliver($o, $payload, 'credit-denied')->assertStatus(409);
        $this->assertDatabaseCount('finance_collections', 0);
        $this->deliver($o, $payload + ['confirmCreditBalance' => true], 'credit-confirmed')->assertOk();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.type', 2);
    }

    public function test_paid_delivery_cancel_reverses_only_linked_collection_once(): void
    {
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '100.00', 'paymentMethod' => 0])->assertOk();
        $d = Delivery::query()->firstOrFail();
        $this->cancel($d->id)->assertStatus(409);
        $this->assertSame(DeliveryStatus::ACTIVE, $d->fresh()->status);
        $this->cancel($d->id, ['reason' => 'Wrong recipient', 'fundsReturnConfirmed' => true])->assertOk();
        $this->cancel($d->id, ['reason' => 'Wrong recipient', 'fundsReturnConfirmed' => true])->assertOk();
        $this->assertSame(DeliveryStatus::CANCELLED, $d->fresh()->status);
        $this->assertSame(OrderProductionStatus::READY_FOR_DELIVERY, $o->fresh()->production_status);
        $this->assertNull($o->fresh()->delivered_at);
        $this->assertSame(CollectionStatus::REVERSED, Collection::findOrFail($d->collection_id)->status);
        $this->assertDatabaseCount('finance_ledger_entries', 3);
        $this->getJson('/api/v1/deliveries/'.$d->id)->assertOk()
            ->assertJsonPath('data.delivery.receivedAmount', '100.00')
            ->assertJsonPath('data.delivery.effectiveReceivedAmount', '0.00')
            ->assertJsonPath('data.delivery.status', 2);
        $this->cancel($d->id, ['reason' => 'Repeated cancel', 'fundsReturnConfirmed' => true], 'new-cancel-key')->assertStatus(409);
    }

    public function test_cancel_without_payment_keeps_finance_ledger_unchanged_and_allows_redelivery(): void
    {
        $o = $this->ready();
        $this->deliver($o)->assertOk();
        $old = Delivery::query()->firstOrFail();
        $this->cancel($old->id)->assertOk();
        $this->assertDatabaseCount('finance_ledger_entries', 1);
        $this->deliver($o, [], 'second-delivery')->assertOk();
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertSame(DeliveryStatus::CANCELLED, $old->fresh()->status);
        $this->assertSame(OrderProductionStatus::DELIVERED, $o->fresh()->production_status);
        $this->getJson('/api/v1/deliveries')->assertJsonPath('data.pagination.total', 2);
    }

    public function test_previously_reversed_collection_is_not_reversed_again_on_cancellation(): void
    {
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '25.00', 'paymentMethod' => 0])->assertOk();
        $d = Delivery::query()->firstOrFail();
        $this->withHeader('Idempotency-Key', 'finance-reverse-manual')
            ->postJson('/api/v1/finance/collections/'.$d->collection_id.'/reverse',
                ['reason' => 'Finance corrected payment'])->assertOk();
        $this->cancel($d->id, ['reason' => 'Cancel after reversal'])->assertOk();
        $this->assertDatabaseCount('finance_ledger_entries', 3);
    }

    public function test_history_filters_by_delivery_date_and_search(): void
    {
        $o = $this->ready();
        $this->deliver($o)->assertOk();
        $today = now('Africa/Cairo')->toDateString();
        $yesterday = now('Africa/Cairo')->subDay()->toDateString();
        $tomorrow = now('Africa/Cairo')->addDay()->toDateString();
        $this->getJson('/api/v1/deliveries?filter[dateFrom]='.$today)
            ->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/v1/deliveries?filter[dateTo]='.$yesterday)
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/deliveries?filter[dateFrom]='.$tomorrow)
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/deliveries?filter[dateFrom]='.$tomorrow.'&filter[dateTo]='.$yesterday)
            ->assertUnprocessable()->assertJsonValidationErrors('filter.dateTo');
        $this->getJson('/api/v1/deliveries?filter[search]='.$o->order_number)
            ->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/v1/deliveries?filter[status]=2')
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_negative_validation_payment_method_and_idempotency_reuse(): void
    {
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '5.00'])->assertUnprocessable()
            ->assertJsonValidationErrors('paymentMethod');
        $this->deliver($o, ['collectedAmount' => '5.00', 'paymentMethod' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors('paymentDestinationId');
        $this->deliver($o, ['collectedAmount' => 10, 'paymentMethod' => 0])->assertUnprocessable()
            ->assertJsonValidationErrors('collectedAmount');
        $this->deliver($o, ['collectedAmount' => '0.00', 'paymentMethod' => 0])->assertUnprocessable()
            ->assertJsonValidationErrors('paymentMethod');
        $this->deliver($o, ['collectedAmount' => '0.00'], 'key-reused')->assertOk();
        $this->deliver($o, ['collectedAmount' => '0.00', 'receiverName' => 'Other'], 'key-reused')->assertStatus(409);
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_payment_needs_finance_permission_but_free_handover_does_not(): void
    {
        $this->adminRole->revokePermissionTo('finance.collections.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '10.00', 'paymentMethod' => 0])->assertForbidden();
        $this->deliver($o, [], 'no-payment-without-finance')->assertOk();
    }

    public function test_cancel_only_requires_deliveries_cancel_not_general_finance_reverse_permission(): void
    {
        $this->adminRole->revokePermissionTo('finance.collections.reverse');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '10.00', 'paymentMethod' => 0])->assertOk();
        $this->cancel(Delivery::firstOrFail()->id,
            ['reason' => 'Operator mistake', 'fundsReturnConfirmed' => true])->assertOk();
        $this->assertDatabaseCount('finance_ledger_entries', 3);
    }
    public function test_bank_and_wallet_destinations_validate_category_and_post_receipts(): void
    {
        $bank = \Modules\Settings\Models\BankAccount::query()->create([
            'bank_name' => 'Main Bank', 'account_number' => '1234567890',
            'is_default' => false, 'status' => \Modules\Settings\Enums\PaymentDestinationStatus::ACTIVE,
        ]);
        $wallet = \Modules\Settings\Models\EWallet::query()->create([
            'name' => 'Wallet', 'phone' => '01098765432', 'account_holder' => 'Treasurer',
            'is_default' => false, 'status' => \Modules\Settings\Enums\PaymentDestinationStatus::ACTIVE,
        ]);
        $first = $this->ready();
        $second = $this->ready();
        $this->deliver($first, ['collectedAmount' => '10.00', 'paymentMethod' => 1,
            'paymentDestinationId' => $bank->id], 'paid-bank')->assertOk();
        $this->deliver($second, ['collectedAmount' => '10.00', 'paymentMethod' => 2,
            'paymentDestinationId' => $wallet->id], 'paid-wallet')->assertOk();
        $this->assertDatabaseHas('finance_collections',
            ['order_id' => $first->id, 'bank_account_id' => $bank->id, 'e_wallet_id' => null]);
        $this->assertDatabaseHas('finance_collections',
            ['order_id' => $second->id, 'bank_account_id' => null, 'e_wallet_id' => $wallet->id]);
    }

    public function test_wrong_destination_type_is_422_and_disabled_destination_is_409_without_breaking_replay(): void
    {
        $wallet = \Modules\Settings\Models\EWallet::query()->create([
            'name' => 'Unrelated Wallet', 'phone' => '01090001111', 'account_holder' => 'Cashier',
            'is_default' => false, 'status' => \Modules\Settings\Enums\PaymentDestinationStatus::ACTIVE,
        ]);
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '5.00', 'paymentMethod' => 1,
            'paymentDestinationId' => $wallet->id], 'bad-type')->assertUnprocessable()
            ->assertJsonValidationErrors('paymentDestinationId');

        $bank = \Modules\Settings\Models\BankAccount::query()->create([
            'bank_name' => 'Main Bank', 'account_number' => '1234567890',
            'is_default' => false, 'status' => \Modules\Settings\Enums\PaymentDestinationStatus::ACTIVE,
        ]);
        $paid = ['collectedAmount' => '5.00', 'paymentMethod' => 1, 'paymentDestinationId' => $bank->id];
        $this->deliver($o, $paid, 'bank-replay')->assertOk();
        $bank->status = \Modules\Settings\Enums\PaymentDestinationStatus::INACTIVE;
        $bank->save();
        $this->deliver($o, $paid, 'bank-replay')->assertOk();

        $another = $this->ready();
        $this->deliver($another, $paid, 'disabled-bank')->assertStatus(409);
        $this->assertDatabaseCount('finance_collections', 1);
    }

    public function test_balance_and_collection_metadata_are_hidden_without_finance_view_permission(): void
    {
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '15.00', 'paymentMethod' => 0])->assertOk();
        $d = Delivery::query()->firstOrFail();
        $this->adminRole->revokePermissionTo('finance.balances.view');
        $this->adminRole->revokePermissionTo('finance.collections.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson('/api/v1/deliveries/'.$d->id)->assertOk()
            ->assertJsonPath('data.delivery.collection', null)
            ->assertJsonPath('data.delivery.receiptNumber', null)
            ->assertJsonPath('data.delivery.receivedAmount', '15.00');
        $other = $this->ready();
        $this->getJson('/api/v1/deliveries/ready/'.$other->id)->assertOk()
            ->assertJsonPath('data.customerBalance', null);
    }

    public function test_delivery_timestamp_is_persisted_in_utc_and_local_day_matches_cairo(): void
    {
        $o = $this->ready();
        // Midnight in Cairo on 25 October is still the preceding UTC day.
        $instant = \Carbon\CarbonImmutable::parse('2026-10-25 00:30:00', 'Africa/Cairo');
        \Carbon\CarbonImmutable::setTestNow($instant);
        try {
            $this->deliver($o, [], 'cairo-midnight')->assertOk();
        } finally {
            \Carbon\CarbonImmutable::setTestNow();
        }
        $delivery = Delivery::query()->firstOrFail();
        $this->assertSame('2026-10-24 21:30:00', \Illuminate\Support\Facades\DB::table('deliveries')
            ->where('id', $delivery->id)->value('delivered_at'));
        $this->assertSame('2026-10-25', $delivery->delivered_local_date->format('Y-m-d'));
        $this->assertSame('2026-10-25', $o->fresh()->delivered_local_date->format('Y-m-d'));
        $this->getJson('/api/v1/deliveries/'.$delivery->id)->assertOk()
            ->assertJsonPath('data.delivery.deliveredLocalDate', '2026-10-25')
            ->assertJsonPath('data.delivery.deliveredAt', '2026-10-25T00:30:00+03:00');
    }

    public function test_financial_balance_check_uses_current_locking_read_on_mysql(): void
    {
        $o = $this->ready();
        $sql = [];
        \Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $q) use (&$sql): void {
            $sql[] = strtolower($q->sql);
        });
        $this->deliver($o, ['collectedAmount' => '50.00', 'paymentMethod' => 0], 'locking-balance')->assertOk();
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            $this->assertTrue(collect($sql)->contains(static fn (string $query): bool =>
                str_contains($query, 'finance_ledger_entries') &&
                str_contains($query, 'signed_amount') &&
                str_contains($query, 'for update')
            ), 'The ledger must be read with a current locking read after the customer lock.');
        }
    }

    public function test_failed_collection_rolls_back_delivery_and_order_state(): void
    {
        $bank = \Modules\Settings\Models\BankAccount::query()->create([
            'bank_name' => 'Disabled Bank', 'account_number' => '12345555', 'is_default' => false,
            'status' => \Modules\Settings\Enums\PaymentDestinationStatus::INACTIVE,
        ]);
        $o = $this->ready();
        $this->deliver($o, ['collectedAmount' => '25.00', 'paymentMethod' => 1,
            'paymentDestinationId' => $bank->id], 'rollback-bank')->assertStatus(409);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertDatabaseCount('finance_ledger_entries', 1); // only order liability
        $this->assertSame(OrderProductionStatus::READY_FOR_DELIVERY, $o->fresh()->production_status);
        $this->assertNull($o->fresh()->delivered_at);
    }

    public function test_unknown_order_deliver_returns_not_found_without_side_effects(): void
    {
        $this->withHeader('Idempotency-Key', 'unknown-order-deliver')
            ->postJson('/api/v1/deliveries/orders/987654321/deliver', [])
            ->assertNotFound();
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('finance_collections', 0);
    }

    public function test_delivery_date_filter_uses_indexable_date_column_comparison(): void
    {
        $o = $this->ready();
        $this->deliver($o)->assertOk();
        $statements = [];
        \Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $q) use (&$statements): void {
            $statements[] = strtolower($q->sql);
        });
        $today = now('Africa/Cairo')->toDateString();
        $this->getJson('/api/v1/deliveries?filter[dateFrom]='.$today.'&filter[dateTo]='.$today)
            ->assertOk()->assertJsonPath('data.pagination.total', 1);
        $filtered = collect($statements)->filter(static fn (string $q): bool =>
            str_contains($q, 'deliveries') && str_contains($q, 'delivered_local_date'));
        $this->assertTrue($filtered->isNotEmpty());
        $this->assertFalse($filtered->contains(static fn (string $q): bool =>
            str_contains($q, 'date(')), 'Date-column filters should not wrap an indexed DATE with DATE().');
    }

}
