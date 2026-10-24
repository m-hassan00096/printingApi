<?php
namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Contracts\ConfirmOrderFinanceData;
use Modules\Orders\Contracts\CancelOrderFinanceData;
use Modules\Orders\Services\UnavailableOrderFinanceGateway;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderFinanceGateway;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrdersStatusSelectionTest extends OrdersTestCase
{
    public function test_create_accepts_explicit_draft_status_without_finance(): void
    {
        $this->postJson('/api/v1/orders', $this->data(['status' => 0]))
            ->assertCreated()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', __('orders.messages.draft_created'));

        $draft = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::DRAFT, $draft->status);
        $this->assertNull($draft->order_number);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_daily_sequences', 0);
    }

    public function test_create_confirmed_without_finance_rolls_back_draft_items_and_idempotency(): void
    {
        $this->app->instance(OrderFinanceGateway::class, new UnavailableOrderFinanceGateway());
        $this->withHeader('Idempotency-Key', 'direct-confirm-no-finance')
            ->postJson('/api/v1/orders', $this->data([
                'status' => 1,
                'depositAmount' => '75.00', 'paymentMethod' => 0,
            ]))->assertStatus(409)
            ->assertJsonPath('message', __('orders.messages.finance_unavailable'));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_activities', 0);
        $this->assertDatabaseCount('order_idempotency_keys', 0);
        $this->assertDatabaseCount('order_daily_sequences', 0);
    }

    public function test_update_status_confirmed_without_finance_rolls_back_all_header_changes(): void
    {
        $draft = $this->draft();
        $original = $draft->fresh();

        $this->app->instance(OrderFinanceGateway::class, new UnavailableOrderFinanceGateway());
        $this->withHeader('Idempotency-Key', 'update-confirm-no-finance')
            ->patchJson($this->path($draft), [
                'status' => 1,
                'customerId' => $this->secondCustomer->id,
                'notes' => 'Must roll back',
                'depositAmount' => '25.00', 'paymentMethod' => 0,
            ])->assertStatus(409)
            ->assertJsonPath('message', __('orders.messages.finance_unavailable'));

        $draft->refresh();
        $this->assertSame(OrderStatus::DRAFT, $draft->status);
        $this->assertSame($original->customer_id, $draft->customer_id);
        $this->assertSame($original->notes, $draft->notes);
        $this->assertSame($original->customer_type_id_snapshot, $draft->customer_type_id_snapshot);
        $this->assertNull($draft->confirmed_at);
        $this->assertNull($draft->order_number);
        $this->assertDatabaseCount('order_activities', 1);
        $this->assertDatabaseCount('order_daily_sequences', 0);
    }

    public function test_create_confirmed_with_finance_adapter_returns_number_and_replays_without_reposting(): void
    {
        $fake = $this->fakeFinance();
        $payload = $this->data(['status' => 1, 'depositAmount' => '100.00', 'paymentMethod' => 0]);
        $this->withHeader('Idempotency-Key', 'direct-confirm-with-gateway');

        $this->postJson('/api/v1/orders', $payload)->assertCreated()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', __('orders.messages.confirmed'));

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertSame('350.00', (string) $order->total_amount);
        $this->assertSame(0, $order->production_status->value);
        $number = $order->order_number;
        $this->assertMatchesRegularExpression('/^ORD-[0-9]{6}001$/', $number);

        $this->postJson('/api/v1/orders', $payload)->assertCreated()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', __('orders.messages.confirmed'));

        $this->assertSame($number, $order->fresh()->order_number);
        $this->assertSame(1, $fake->calls);
        $this->assertSame('100.00', $fake->lastDeposit);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_idempotency_keys', 1);
        $this->assertDatabaseCount('order_daily_sequences', 1);
        $this->assertDatabaseCount('order_activities', 2);
        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'event_type' => 'order_confirmed',
        ]);
    }

    public function test_update_draft_to_confirmed_with_finance_adapter_returns_updated_order(): void
    {
        $draft = $this->draft();
        $fake = $this->fakeFinance();

        $this->withHeader('Idempotency-Key', 'update-confirm-with-gateway')
            ->patchJson($this->path($draft), [
                'status' => 1,
                'customerId' => $this->secondCustomer->id,
                'notes' => 'Confirmed from edit',
                'depositAmount' => '50.00', 'paymentMethod' => 0,
            ])->assertOk()
            ->assertJsonPath('message', __('orders.messages.confirmed'))
            ->assertJsonPath('data', null);

        $draft->refresh();
        $this->assertSame(OrderStatus::CONFIRMED, $draft->status);
        $this->assertSame(0, $draft->production_status->value);
        $this->assertSame($this->secondCustomer->id, $draft->customer_id);
        $this->assertSame($this->retail->id, $draft->customer_type_id_snapshot);
        $this->assertSame('350.00', (string) $draft->total_amount);
        $this->assertSame('Confirmed from edit', $draft->notes);
        $this->assertMatchesRegularExpression('/^ORD-[0-9]{6}001$/', $draft->order_number);
        $this->assertSame(1, $fake->calls);
        $this->assertSame('50.00', $fake->lastDeposit);
        $this->assertDatabaseHas('order_activities', [
            'order_id' => $draft->id, 'event_type' => 'order_confirmed',
        ]);

        $this->patchJson($this->path($draft), ['status' => 0])->assertStatus(409);
    }

    public function test_create_update_reject_cancelled_status_and_deposit_on_draft(): void
    {
        $this->postJson('/api/v1/orders', $this->data(['status' => 2]))
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->postJson('/api/v1/orders', $this->data([
            'status' => 0, 'depositAmount' => '10.00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('depositAmount');

        $draft = $this->draft();
        $this->patchJson($this->path($draft), ['status' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->patchJson($this->path($draft), [
            'status' => 0, 'depositAmount' => '10.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('depositAmount');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_update_to_confirmed_requires_idempotency_key(): void
    {
        $draft = $this->draft();

        $this->withHeader('Idempotency-Key', '')
            ->patchJson($this->path($draft), ['status' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key');

        $this->assertSame(OrderStatus::DRAFT, $draft->fresh()->status);
    }

    public function test_status_confirmed_requires_confirmation_permission_on_create_and_update(): void
    {
        $draft = $this->draft();
        $staff = User::create([
            'name' => 'Order Editor',
            'username' => 'order_editor_status',
            'password' => 'password123',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->staffRole->givePermissionTo([
            Permission::findOrCreate('orders.create', 'web'),
            Permission::findOrCreate('orders.update', 'web'),
        ]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);

        $this->postJson('/api/v1/orders', $this->data(['status' => 1]))
            ->assertForbidden();

        $this->patchJson($this->path($draft), ['status' => 1])
            ->assertForbidden();

        $this->postJson('/api/v1/orders', $this->data(['status' => 0]))
            ->assertCreated()->assertJsonPath('data', null);

        $this->assertDatabaseCount('orders', 2);
    }

    private function fakeFinance(): object
    {
        // Test-only adapter simulates a successful Finance integration;
        // production uses the real Finance implementation.
        $adapter = new class implements OrderFinanceGateway
        {
            public int $calls = 0;
            public ?string $lastDeposit = null;

            public function recordConfirmation(ConfirmOrderFinanceData $data): void
            {
                $this->calls++;
                $this->lastDeposit = $data->depositAmount;
            }

            public function recordCancellation(CancelOrderFinanceData $data): void {}
        };

        $this->app->instance(OrderFinanceGateway::class, $adapter);

        return $adapter;
    }
}
