<?php

namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrderDraftPaymentPlanTest extends OrdersTestCase
{
    public function test_planned_payment_can_be_created_and_updated_without_financial_posting(): void
    {
        $this->postJson('/api/v1/orders', $this->data([
            'plannedDepositAmount' => '50.00',
            'plannedPaymentMethod' => 0,
        ]))->assertCreated();
        $order = Order::latest('id')->firstOrFail();
        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.plannedDepositAmount', '50.00')
            ->assertJsonPath('data.plannedPaymentMethod', 0)
            ->assertJsonPath('data.plannedPaymentDestinationId', null)
            ->assertJsonPath('data.status', 0);

        $this->patchJson($this->path($order), [
            'plannedDepositAmount' => '120.00',
            'plannedPaymentMethod' => 0,
        ])->assertOk()->assertJsonPath('data', null);
        $this->getJson($this->path($order))->assertJsonPath('data.plannedDepositAmount', '120.00');
        $this->assertDatabaseCount('order_daily_sequences', 0);
        $this->assertNull($order->fresh()->confirmed_at);
    }

    public function test_draft_payment_plan_requires_method_for_positive_amount(): void
    {
        $order = $this->draft();
        $this->patchJson($this->path($order), ['plannedDepositAmount' => '20.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('plannedPaymentMethod');

        $this->patchJson($this->path($order), [
            'plannedDepositAmount' => '20.00', 'plannedPaymentMethod' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('plannedPaymentDestinationId');

        $this->patchJson($this->path($order), [
            'plannedDepositAmount' => '0',
        ])->assertOk();
        $this->getJson($this->path($order))->assertJsonPath('data.plannedDepositAmount', '0.00')
            ->assertJsonPath('data.plannedPaymentMethod', null);
    }

    public function test_draft_payment_intent_cannot_be_sent_as_confirmed_payment(): void
    {
        $this->postJson('/api/v1/orders', $this->data([
            'status' => 1,
            'plannedDepositAmount' => '50.00',
            'plannedPaymentMethod' => 0,
        ]))->assertUnprocessable()->assertJsonValidationErrors('plannedDepositAmount');

        $order = $this->draft([
            'plannedDepositAmount' => '50.00', 'plannedPaymentMethod' => 0,
        ]);
        $this->withHeader('Idempotency-Key', 'draft-pay-no-auto-apply')
            ->postJson($this->path($order).'/confirm', [])->assertOk();

        $confirmed = $order->fresh();
        $this->assertNull($confirmed->planned_deposit_amount);
        $this->assertNull($confirmed->planned_payment_method);
        $this->getJson($this->path($order))
            ->assertJsonPath('data.plannedDepositAmount', null);
    }
}
