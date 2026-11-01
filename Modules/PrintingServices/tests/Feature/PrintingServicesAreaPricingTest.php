<?php

namespace Modules\PrintingServices\Tests\Feature;

require_once __DIR__.'/Support/PrintingServiceTestCase.php';

use Modules\PrintingServices\Tests\Feature\Support\PrintingServiceTestCase;

class PrintingServicesAreaPricingTest extends PrintingServiceTestCase
{
    public function test_area_preview_selects_quantity_tier_and_multiplies_area(): void
    {
        $result = $this->postJson('/api/v1/printing-services', $this->payload([
            'pricingMethod' => 'square_centimeter',
            'basePrice' => '0.500000',
            'priceTiers' => [
                ['customerTypeId' => $this->wholesale->id, 'minQuantity' => 10, 'maxQuantity' => 49, 'price' => '0.450000'],
            ],
        ]))->assertCreated();
        $serviceId = $result->json('data.id');

        $this->postJson('/api/v1/printing-services/price-preview', [
            'serviceId' => $serviceId,
            'customerTypeId' => $this->wholesale->id,
            'quantity' => 20,
            'widthCm' => '20',
            'heightCm' => '15',
        ])->assertOk()
            ->assertJsonPath('data.pricingMethod', 'square_centimeter')
            ->assertJsonPath('data.area', '300')
            ->assertJsonPath('data.suggestedUnitPrice', '0.45')
            ->assertJsonPath('data.suggestedTotal', '2700.00')
            ->assertJsonPath('data.source', 'customerTypeQuantityTier');
    }

    public function test_square_meter_preview_uses_centimeters_and_total_only_rounding(): void
    {
        $result = $this->postJson('/api/v1/printing-services', $this->payload([
            'pricingMethod' => 'square_meter',
            'basePrice' => '100',
        ]))->assertCreated();

        $this->postJson('/api/v1/printing-services/price-preview', [
            'serviceId' => $result->json('data.id'),
            'customerTypeId' => $this->wholesale->id,
            'quantity' => 3,
            'widthCm' => '200',
            'heightCm' => '150',
        ])->assertOk()->assertJsonPath('data.suggestedTotal', '900.00');
    }

    public function test_area_pricing_requires_both_dimensions(): void
    {
        $result = $this->postJson('/api/v1/printing-services', $this->payload([
            'pricingMethod' => 'square_centimeter',
            'basePrice' => '0.025',
        ]))->assertCreated();

        $this->postJson('/api/v1/printing-services/price-preview', [
            'serviceId' => $result->json('data.id'),
            'quantity' => 10,
            'widthCm' => '20',
        ])->assertUnprocessable()->assertJsonValidationErrors('heightCm');
    }

    public function test_new_rates_are_required_when_existing_service_method_changes(): void
    {
        $created = $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [['customerTypeId' => null, 'minQuantity' => 10, 'maxQuantity' => 100, 'price' => '5.00']],
        ]))->assertCreated();
        $serviceId = $created->json('data.id');

        $this->patchJson('/api/v1/printing-services/'.$serviceId, [
            'pricingMethod' => 'square_centimeter',
            'basePrice' => '0.025',
        ])->assertUnprocessable()->assertJsonValidationErrors('priceTiers');
    }
}
