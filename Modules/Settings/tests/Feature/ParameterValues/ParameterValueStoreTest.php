<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class ParameterValueStoreTest extends ParameterValueTestCase
{
    public function test_can_create_a_printing_location_and_record_the_creator(): void
    {
        $this->postJson($this->url(), $this->payload([
            'name' => '  Print House A  ',
        ]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.parameterId', $this->printingLocations->id)
            ->assertJsonPath('data.name', 'Print House A')
            ->assertJsonPath('data.status', ParameterValueStatus::ACTIVE->value);

        $value = ParameterValue::query()->where('name', 'Print House A')->firstOrFail();

        $this->assertSame($this->printingLocations->id, $value->parameter_id);
        $this->assertSame(ParameterValueStatus::ACTIVE, $value->status);
        $this->assertSame($this->administrator->id, $value->created_by);
        $this->assertNull($value->updated_by);
    }

    public function test_printing_location_can_have_description(): void
    {
        $this->postJson($this->url(), $this->payload([
            'description' => 'Supports large format color printing',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.description', 'Supports large format color printing');

        $this->assertDatabaseHas('parameter_values', [
            'parameter_id' => $this->printingLocations->id,
            'name' => 'Print House A',
            'description' => 'Supports large format color printing',
        ]);
    }

    public function test_can_create_customer_type_with_optional_description(): void
    {
        $this->postJson($this->url(), $this->payload([
            'parameterId' => $this->customerTypes->id,
            'name' => 'Company',
            'description' => 'Business customers',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.parameterId', $this->customerTypes->id)
            ->assertJsonPath('data.description', 'Business customers');

        $this->assertDatabaseHas('parameter_values', [
            'parameter_id' => $this->customerTypes->id,
            'name' => 'Company',
            'description' => 'Business customers',
        ]);
    }

    public function test_can_create_an_inactive_value(): void
    {
        $this->postJson($this->url(), $this->payload([
            'status' => ParameterValueStatus::INACTIVE->value,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 0);

        $this->assertSame(
            ParameterValueStatus::INACTIVE,
            ParameterValue::query()->firstOrFail()->status,
        );
    }

    public function test_duplicate_name_within_same_group_is_rejected_after_trimming(): void
    {
        $this->createValue($this->printingLocations, 'Print House A');

        $this->postJson($this->url(), $this->payload([
            'name' => '  Print House A  ',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('parameter_values', 1);
    }

    public function test_same_name_in_different_groups_is_allowed(): void
    {
        $this->createValue($this->customerTypes, 'Print House A');

        $this->postJson($this->url(), $this->payload())
            ->assertCreated();

        $this->assertDatabaseCount('parameter_values', 2);
    }

    public function test_unknown_parameter_id_is_rejected(): void
    {
        $this->postJson($this->url(), $this->payload([
            'parameterId' => 999999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameterId');

        $this->assertDatabaseCount('parameter_values', 0);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->postJson($this->url(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parameterId', 'name', 'status']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->postJson($this->url(), $this->payload([
            'status' => 5,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('parameter_values', 0);
    }

    public function test_snake_case_parameter_id_cannot_override_the_group(): void
    {
        $this->postJson($this->url(), $this->payload([
            'parameter_id' => $this->customerTypes->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameter_id');

        $this->assertDatabaseCount('parameter_values', 0);
    }

    public function test_guest_cannot_create_parameter_values(): void
    {
        $this->logoutForTest();

        $this->postJson($this->url(), $this->payload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('parameter_values', 0);
    }

    public function test_user_without_create_permission_cannot_create_parameter_values(): void
    {
        $this->loginAs($this->createStaff());

        $this->postJson($this->url(), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('parameter_values', 0);
    }

    public function test_switching_from_admin_session_to_staff_yields_forbidden_not_unauthorized(): void
    {
        // Authenticate the administrator via an actual request so Sanctum stores
        // the admin password hash in the session before switching users.
        $this->postJson($this->url(), $this->payload())->assertCreated();

        $this->loginAs($this->createStaff());

        $this->postJson($this->url(), $this->payload(['name' => 'Another Print House']))
            ->assertForbidden();
    }

    public function test_update_only_permission_does_not_allow_creation(): void
    {
        $this->staffRole->givePermissionTo('settings.update_parameter_value');
        $this->loginAs($this->createStaff());

        $this->postJson($this->url(), $this->payload())
            ->assertForbidden();
    }
}
