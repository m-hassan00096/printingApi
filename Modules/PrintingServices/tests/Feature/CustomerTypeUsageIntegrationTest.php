<?php

namespace Modules\PrintingServices\Tests\Feature;

require_once __DIR__.'/Support/PrintingServiceTestCase.php';

use Modules\Identity\Models\Permission;
use Modules\PrintingServices\Models\PrintingServicePriceTier;
use Modules\PrintingServices\Tests\Feature\Support\PrintingServiceTestCase;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;
use Spatie\Permission\PermissionRegistrar;

class CustomerTypeUsageIntegrationTest extends PrintingServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Settings owns the delete endpoint; PrintingServices only registers a guard.
        $this->adminRole->givePermissionTo(
            Permission::findOrCreate('settings.delete_parameter_value', 'web'),
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_cannot_delete_customer_type_referenced_by_a_live_tier(): void
    {
        app()->setLocale('ar');
        $service = $this->service();
        $this->tier($service, ['customer_type_id' => $this->wholesale->id]);

        $this->deleteJson($this->deleteUrl($this->wholesale->id))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'لا يمكن حذف نوع العميل لأنه مستخدم في شرائح أسعار خدمات الطباعة.',
            );

        $this->assertNotSoftDeleted('parameter_values', ['id' => $this->wholesale->id]);
    }

    public function test_soft_deleted_tier_still_reserves_its_historical_customer_type(): void
    {
        $service = $this->service();
        $id = $this->tier($service, ['customer_type_id' => $this->wholesale->id]);
        PrintingServicePriceTier::findOrFail($id)->delete();

        $this->deleteJson($this->deleteUrl($this->wholesale->id))
            ->assertStatus(409);

        $this->assertNotSoftDeleted('parameter_values', ['id' => $this->wholesale->id]);
    }

    public function test_can_delete_unreferenced_customer_type_without_changing_settings_permissions(): void
    {
        $this->deleteJson($this->deleteUrl($this->retail->id))
            ->assertOk();

        $this->assertSoftDeleted('parameter_values', ['id' => $this->retail->id]);
    }

    public function test_other_settings_groups_are_unaffected_by_printing_usage_guard(): void
    {
        $service = $this->service();
        $this->tier($service, ['customer_type_id' => $this->wholesale->id]);

        $otherGroup = Parameter::firstOrCreate(['name' => 'printing_locations']);
        $location = $otherGroup->values()->create([
            'name' => 'Main Branch',
            'description' => null,
            'status' => ParameterValueStatus::ACTIVE,
        ]);

        $this->deleteJson($this->deleteUrl($location->id))->assertOk();
        $this->assertSoftDeleted('parameter_values', ['id' => $location->id]);
        $this->assertNotSoftDeleted('parameter_values', ['id' => $this->wholesale->id]);
    }

    public function test_inactive_customer_type_still_cannot_be_deleted_when_referenced(): void
    {
        $service = $this->service();
        $this->tier($service, ['customer_type_id' => $this->wholesale->id]);

        $this->wholesale->status = ParameterValueStatus::INACTIVE;
        $this->wholesale->save();

        $this->deleteJson($this->deleteUrl($this->wholesale->id))
            ->assertStatus(409);

        $this->assertNotSoftDeleted('parameter_values', ['id' => $this->wholesale->id]);
    }

    public function test_create_rejects_a_soft_deleted_customer_type(): void
    {
        $this->wholesale->delete();

        $this->postJson('/api/v1/printing-services', $this->payload([
            'priceTiers' => [[
                'customerTypeId' => $this->wholesale->id,
                'minQuantity' => 1,
                'maxQuantity' => 50,
                'price' => '5.00',
            ]],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('priceTiers.0.customerTypeId');

        $this->assertDatabaseCount('printing_service_price_tiers', 0);
        $this->assertDatabaseCount('printing_services', 0);
    }

    public function test_batch_update_rejects_deleted_customer_type_and_rolls_back_service_changes(): void
    {
        $service = $this->service();
        $this->retail->delete();

        $this->patchJson('/api/v1/printing-services/'.$service->id, [
            'name' => 'Uncommitted Name',
            'priceTiers' => [[
                'actionStatus' => 1,
                'customerTypeId' => $this->retail->id,
                'minQuantity' => 1,
                'maxQuantity' => 200,
                'price' => '3.50',
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('priceTiers.0.customerTypeId');

        $this->assertDatabaseHas('printing_services', [
            'id' => $service->id,
            'name' => 'Business Cards',
        ]);
        $this->assertDatabaseCount('printing_service_price_tiers', 0);
    }

    public function test_deleting_a_service_does_not_allow_deleting_historically_referenced_customer_type(): void
    {
        $service = $this->service();
        $this->tier($service, ['customer_type_id' => $this->wholesale->id]);

        $this->deleteJson('/api/v1/printing-services/'.$service->id)
            ->assertOk();

        $this->deleteJson($this->deleteUrl($this->wholesale->id))
            ->assertStatus(409);
    }

    private function deleteUrl(int $id): string
    {
        return '/api/v1/settings/parameter-values/'.$id;
    }
}

