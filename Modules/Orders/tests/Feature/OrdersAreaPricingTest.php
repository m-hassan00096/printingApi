<?php

namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Modules\PrintingServices\Enums\PricingMethod;

class OrdersAreaPricingTest extends OrdersTestCase
{
    private function areaService(): void
    {
        $this->service->pricing_method = PricingMethod::SQUARE_CENTIMETER;
        $this->service->base_price = '0.025';
        $this->service->save();
    }

    public function test_draft_saves_dimensions_rate_snapshot_and_agreed_price_independently(): void
    {
        $this->areaService();
        $response = $this->postJson('/api/v1/orders', $this->data([
            'items' => [[
                'serviceId' => $this->service->id,
                'quantity' => 10,
                'widthCm' => '20',
                'heightCm' => '15',
                'agreedTotal' => '70.00',
            ]],
        ]))->assertCreated();
        $order = \Modules\Orders\Models\Order::query()->latest('id')->firstOrFail();

        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.items.0.pricingMethod', 'square_centimeter')
            ->assertJsonPath('data.items.0.widthCm', '20.0000')
            ->assertJsonPath('data.items.0.heightCm', '15.0000')
            ->assertJsonPath('data.items.0.pricingRateSnapshot', '0.025000')
            ->assertJsonPath('data.items.0.agreedTotal', '70.00')
            ->assertJsonPath('data.totalAmount', '70.00');
    }

    public function test_area_items_reject_missing_dimensions(): void
    {
        $this->areaService();
        $this->postJson('/api/v1/orders', $this->data([
            'items' => [[
                'serviceId' => $this->service->id,
                'quantity' => 5,
                'widthCm' => '10',
                'agreedTotal' => '20.00',
            ]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('heightCm');
    }

    public function test_updating_only_dimensions_does_not_replace_agreed_total(): void
    {
        $this->areaService();
        $order = $this->draft([
            'items' => [[
                'serviceId' => $this->service->id,
                'quantity' => 10,
                'widthCm' => '20',
                'heightCm' => '15',
                'agreedTotal' => '70.00',
            ]],
        ]);
        $item = $order->items()->firstOrFail();

        $this->patchJson('/api/v1/orders/'.$order->id.'/items/'.$item->id, [
            'widthCm' => '25',
        ])->assertOk();

        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.items.0.widthCm', '25.0000')
            ->assertJsonPath('data.items.0.heightCm', '15.0000')
            ->assertJsonPath('data.items.0.agreedTotal', '70.00');
    }
}
