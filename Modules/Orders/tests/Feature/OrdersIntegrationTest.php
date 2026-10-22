<?php
namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrdersIntegrationTest extends OrdersTestCase
{
    public function test_any_persisted_draft_blocks_customer_deactivation_and_deletion(): void
    {
        $order = $this->draft();

        $this->patchJson('/api/v1/customers/'.$this->customer->id, ['status' => 0])
            ->assertStatus(409)
            ->assertJsonPath('message', __('orders.messages.customer_has_orders'));

        $this->deleteJson('/api/v1/customers/'.$this->customer->id)->assertStatus(409);

        $this->deleteJson($this->path($order))->assertOk();
        $this->deleteJson('/api/v1/customers/'.$this->customer->id)->assertStatus(409);
        $this->patchJson('/api/v1/customers/'.$this->customer->id, ['status' => 0])
            ->assertStatus(409);
    }

    public function test_service_used_by_soft_deleted_order_items_cannot_be_deleted(): void
    {
        $order = $this->draft();
        $this->deleteJson($this->path($order))->assertOk();

        $this->deleteJson('/api/v1/printing-services/'.$this->service->id)
            ->assertStatus(409)->assertJsonPath('message', __('orders.messages.service_in_use'));
        $this->assertNotSoftDeleted('printing_services', ['id' => $this->service->id]);
    }

    public function test_confirm_is_blocked_until_finance_can_post_atomic_liability(): void
    {
        $order = $this->draft();

        $this->withHeader('Idempotency-Key', 'orders-confirm-001')
            ->postJson($this->path($order).'/confirm', ['depositAmount' => '50.00'])
            ->assertStatus(409)
            ->assertJsonPath('message', __('orders.messages.finance_unavailable'));

        $this->assertSame(OrderStatus::DRAFT, $order->fresh()->status);
        $this->assertNull($order->fresh()->order_number);
        $this->assertNull($order->fresh()->confirmed_at);
        $this->assertDatabaseCount('order_daily_sequences', 0);
    }

    public function test_confirm_requires_idempotency_key(): void
    {
        $order = $this->draft();
        $this->postJson($this->path($order).'/confirm')->assertUnprocessable()
            ->assertJsonValidationErrors('Idempotency-Key');
    }

    public function test_cancel_has_no_fake_refund_or_status_change_without_finance(): void
    {
        $order = $this->draft();
        $order->status = OrderStatus::CONFIRMED;
        $order->order_number = 'ORD-261022001';
        $order->confirmed_at = now();
        $order->save();

        $this->withHeader('Idempotency-Key', 'orders-cancel-001')
            ->postJson($this->path($order).'/cancel', ['reason' => 'Customer withdrew'])
            ->assertStatus(409)->assertJsonPath('message', __('orders.messages.finance_unavailable'));

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertNull($order->fresh()->cancelled_at);
    }

    public function test_cannot_modify_confirmed_item_or_order_through_draft_apis(): void
    {
        $order = $this->draft();
        $order->status = OrderStatus::CONFIRMED;
        $order->save();

        $this->patchJson($this->path($order), ['notes' => 'Override'])
            ->assertStatus(409);
        $this->patchJson($this->path($order).'/items/'.$order->items()->first()->id, [
            'agreedTotal' => '400.00',
        ])->assertStatus(409);
        $this->deleteJson($this->path($order))->assertStatus(409);
    }
}
