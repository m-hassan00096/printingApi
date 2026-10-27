<?php

namespace Modules\PrintingServices\Tests\Feature;

require_once __DIR__.'/Support/PrintingServiceTestCase.php';

use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Tests\Feature\Support\PrintingServiceTestCase;

class PrintingServicesPricingTest extends PrintingServiceTestCase
{
    public function test_customer_specific_tiers_precede_generic_tiers_and_base_price(): void
    {
        $service = $this->service();
        $this->tier($service, [
            'customer_type_id' => null, 'min_quantity' => 100,
            'max_quantity' => 500, 'price' => '6.00',
        ]);
        $this->tier($service, [
            'customer_type_id' => $this->wholesale->id, 'min_quantity' => null,
            'max_quantity' => null, 'price' => '8.00',
        ]);
        $this->tier($service, [
            'customer_type_id' => $this->wholesale->id, 'min_quantity' => 100,
            'max_quantity' => 300, 'price' => '5.00',
        ]);

        $this->preview($service->id, $this->wholesale->id, 200)
            ->assertOk()->assertJsonPath('data.source', 'customerTypeQuantityTier')
            ->assertJsonPath('data.suggestedTotal', '1000.00');
        $this->preview($service->id, $this->wholesale->id, 400)
            ->assertOk()->assertJsonPath('data.source', 'customerTypeCatchAll')
            ->assertJsonPath('data.suggestedTotal', '3200.00');
        $this->preview($service->id, $this->retail->id, 200)
            ->assertOk()->assertJsonPath('data.source', 'genericQuantityTier')
            ->assertJsonPath('data.suggestedTotal', '1200.00');
        $this->preview($service->id, $this->retail->id, 700)
            ->assertOk()->assertJsonPath('data.source', 'basePrice')
            ->assertJsonPath('data.suggestedTotal', '7000.00');
    }

    public function test_missing_base_price_defaults_to_zero_and_explicit_zero_stays_free(): void
    {
        $unpriced = $this->service(['basePrice' => null]);
        $this->preview($unpriced->id, $this->wholesale->id, 200)
            ->assertOk()
            ->assertJsonPath('data.source', 'basePrice')
            ->assertJsonPath('data.requiresManualTotal', false)
            ->assertJsonPath('data.suggestedUnitPrice', '0.00')
            ->assertJsonPath('data.suggestedTotal', '0.00');

        $free = $this->service(['name' => 'Free Catalog', 'basePrice' => '0.00']);
        $this->preview($free->id, $this->retail->id, 20)
            ->assertOk()->assertJsonPath('data.requiresManualTotal', false)
            ->assertJsonPath('data.suggestedTotal', '0.00');
    }

    public function test_unclassified_customer_uses_base_price_even_when_generic_tiers_exist(): void
    {
        $service = $this->service(['basePrice' => '12.00']);
        $this->tier($service, [
            'customer_type_id' => null,
            'min_quantity' => null, 'max_quantity' => null, 'price' => '7.00',
        ]);
        $this->preview($service->id, null, 10)
            ->assertOk()
            ->assertJsonPath('data.source', 'basePrice')
            ->assertJsonPath('data.suggestedUnitPrice', '12.00')
            ->assertJsonPath('data.suggestedTotal', '120.00');

        $this->preview($service->id, $this->wholesale->id, 10)
            ->assertOk()
            ->assertJsonPath('data.source', 'genericCatchAll')
            ->assertJsonPath('data.suggestedUnitPrice', '7.00');
    }

    public function test_tier_price_of_five_seventy_five_is_exact_without_float_arithmetic(): void
    {
        $service = $this->service();
        $this->tier($service, ['price' => '5.75', 'min_quantity' => 100, 'max_quantity' => 500]);

        $this->preview($service->id, $this->retail->id, 200)
            ->assertOk()->assertJsonPath('data.suggestedUnitPrice', '5.75')
            ->assertJsonPath('data.suggestedTotal', '1150.00');
    }

    public function test_inactive_service_is_not_eligible_for_new_price_previews(): void
    {
        $service = $this->service(['status' => PrintingServiceStatus::INACTIVE]);
        $this->preview($service->id, $this->retail->id, 10)
            ->assertUnprocessable()->assertJsonValidationErrors('serviceId');
    }

    public function test_wrong_settings_parameter_value_is_rejected_for_tier(): void
    {
        $service = $this->service();
        $this->postJson('/api/v1/printing-services', $this->payload([
            'name' => 'Invalid Type',
            'priceTiers' => [[
                'customerTypeId' => 999999, 'price' => '10.00',
            ]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('priceTiers.0.customerTypeId');
    }

    public function test_active_services_are_registered_in_shared_select_aggregator(): void
    {
        $service = $this->service();
        $this->service(['name' => 'Inactive', 'status' => PrintingServiceStatus::INACTIVE]);

        $this->getJson('/api/v1/selects?selects=printingServices')
            ->assertOk()->assertJsonCount(1, 'data.0.options')
            ->assertJsonPath('data.0.options.0.value', $service->id)
            ->assertJsonPath('data.0.options.0.label', 'Business Cards');
    }

    public function test_cross_service_tier_id_is_rejected_on_update(): void
    {
        $a = $this->service();
        $b = $this->service(['name' => 'Second Service']);
        $bTierId = $this->tier($b);

        $this->patchJson('/api/v1/printing-services/'.$a->id, [
            'priceTiers' => [['actionStatus' => 3, 'priceTierId' => $bTierId]],
        ])->assertUnprocessable()->assertJsonValidationErrors('priceTiers');
    }

    private function preview(int $serviceId, ?int $customerTypeId, int $quantity)
    {
        return $this->postJson('/api/v1/printing-services/price-preview', [
            'serviceId' => $serviceId,
            'customerTypeId' => $customerTypeId,
            'quantity' => $quantity,
        ]);
    }
}

