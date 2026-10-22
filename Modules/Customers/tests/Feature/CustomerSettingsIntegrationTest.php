<?php
namespace Modules\Customers\Tests\Feature;

require_once __DIR__.'/Support/CustomerTestCase.php';

use Modules\Customers\Tests\Feature\Support\CustomerTestCase;
use Modules\Settings\Enums\ParameterValueStatus;

class CustomerSettingsIntegrationTest extends CustomerTestCase
{
    private function deleteType(int $id): string
    {
        return '/api/v1/settings/parameter-values/'.$id;
    }

    public function test_active_customer_blocks_settings_type_deactivation(): void
    {
        $this->customer();

        $this->patchJson($this->deleteType($this->wholesale->id), ['status' => 0])
            ->assertStatus(409)
            ->assertJsonPath('message', __('customers.messages.customer_type_in_use'));

        $this->assertSame(ParameterValueStatus::ACTIVE, $this->wholesale->fresh()->status);
    }

    public function test_active_customer_blocks_soft_deleting_type(): void
    {
        $this->customer();

        $this->deleteJson($this->deleteType($this->wholesale->id))
            ->assertStatus(409)
            ->assertJsonPath('message', __('customers.messages.customer_type_in_use'));
    }

    public function test_soft_deleted_customer_does_not_reserve_its_old_type(): void
    {
        $customer = $this->customer();
        $this->deleteJson('/api/v1/customers/'.$customer->id)->assertOk();

        $this->deleteJson($this->deleteType($this->wholesale->id))->assertOk();
        $this->assertSoftDeleted('parameter_values', ['id' => $this->wholesale->id]);
    }

    public function test_other_settings_groups_are_unaffected(): void
    {
        $this->customer();

        $this->patchJson($this->deleteType($this->printingLocation->id), ['status' => 0])
            ->assertOk()->assertJsonPath('data.status', 0);

        $this->deleteJson($this->deleteType($this->printingLocation->id))->assertOk();
    }

    public function test_reassigning_customer_type_changes_which_type_is_protected(): void
    {
        $customer = $this->customer();
        $this->patchJson('/api/v1/customers/'.$customer->id, [
            'customerTypeId' => $this->retail->id,
        ])->assertOk();

        $this->deleteJson($this->deleteType($this->wholesale->id))->assertOk();
        $this->deleteJson($this->deleteType($this->retail->id))->assertStatus(409);
    }
}
