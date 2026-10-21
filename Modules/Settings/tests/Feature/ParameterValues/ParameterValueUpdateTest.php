<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class ParameterValueUpdateTest extends ParameterValueTestCase
{
    public function test_can_update_selected_fields_and_track_the_editor(): void
    {
        $value = $this->createValue($this->customerTypes, 'Old Type', [
            'description' => 'Keep this description',
        ]);

        $this->patchJson($this->url($value), [
            'name' => '  New Type  ',
            'status' => ParameterValueStatus::INACTIVE->value,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Type')
            ->assertJsonPath('data.description', 'Keep this description')
            ->assertJsonPath('data.status', 0)
            ->assertJsonPath('data.parameterId', $this->customerTypes->id);

        $value->refresh();
        $this->assertSame('New Type', $value->name);
        $this->assertSame('Keep this description', $value->description);
        $this->assertSame(ParameterValueStatus::INACTIVE, $value->status);
        $this->assertSame($this->customerTypes->id, $value->parameter_id);
        $this->assertSame($this->administrator->id, $value->created_by);
        $this->assertSame($this->administrator->id, $value->updated_by);
    }

    public function test_printing_location_description_can_be_added_and_cleared(): void
    {
        $location = $this->createValue($this->printingLocations, 'Main Printing House');

        $this->patchJson($this->url($location), [
            'description' => 'Digital and offset printing',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Digital and offset printing');

        $this->assertSame('Digital and offset printing', $location->fresh()->description);

        $this->patchJson($this->url($location), ['description' => null])
            ->assertOk()
            ->assertJsonPath('data.description', null);

        $this->assertNull($location->fresh()->description);
    }

    public function test_put_can_update_description_without_resetting_other_fields(): void
    {
        $value = $this->createValue($this->customerTypes, 'Company');

        $this->putJson($this->url($value), [
            'description' => 'Corporate account',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Corporate account')
            ->assertJsonPath('data.name', 'Company')
            ->assertJsonPath('data.status', 1);
    }

    public function test_existing_name_can_be_sent_unchanged(): void
    {
        $value = $this->createValue($this->printingLocations, 'Print House A');

        $this->patchJson($this->url($value), [
            'name' => 'Print House A',
        ])->assertOk();

        $this->assertSame('Print House A', $value->fresh()->name);
    }

    public function test_duplicate_name_within_group_is_rejected(): void
    {
        $value = $this->createValue($this->printingLocations, 'Print House A');
        $this->createValue($this->printingLocations, 'Print House B');

        $this->patchJson($this->url($value), [
            'name' => '  Print House B  ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame('Print House A', $value->fresh()->name);
    }

    public function test_same_name_in_different_group_is_allowed(): void
    {
        $value = $this->createValue($this->printingLocations, 'Print House A');
        $this->createValue($this->customerTypes, 'Shared Name');

        $this->patchJson($this->url($value), [
            'name' => 'Shared Name',
        ])->assertOk();

        $this->assertSame('Shared Name', $value->fresh()->name);
    }

    public function test_cannot_move_value_to_another_parameter_group(): void
    {
        $value = $this->createValue($this->printingLocations);

        $this->patchJson($this->url($value), [
            'parameterId' => $this->customerTypes->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameterId');

        $this->assertSame($this->printingLocations->id, $value->fresh()->parameter_id);
    }

    public function test_snake_case_parameter_id_is_also_prohibited(): void
    {
        $value = $this->createValue($this->printingLocations);

        $this->patchJson($this->url($value), [
            'parameter_id' => $this->customerTypes->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameter_id');

        $this->assertSame($this->printingLocations->id, $value->fresh()->parameter_id);
    }

    public function test_invalid_status_is_rejected_on_update(): void
    {
        $value = $this->createValue($this->printingLocations);

        $this->patchJson($this->url($value), [
            'status' => 99,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(ParameterValueStatus::ACTIVE, $value->fresh()->status);
    }

    public function test_name_cannot_be_changed_to_blank(): void
    {
        $value = $this->createValue($this->printingLocations);

        $this->patchJson($this->url($value), [
            'name' => '   ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame('Existing Value', $value->fresh()->name);
    }

    public function test_unknown_value_cannot_be_updated(): void
    {
        $this->patchJson($this->url().'/999999', [
            'name' => 'Never Created',
        ])->assertNotFound();
    }

    public function test_soft_deleted_value_cannot_be_updated(): void
    {
        $value = $this->createValue($this->printingLocations);
        $value->delete();

        $this->patchJson($this->url($value), [
            'name' => 'Must Not Change',
        ])->assertNotFound();

        $this->assertSame('Existing Value', ParameterValue::withTrashed()->findOrFail($value->id)->name);
    }

    public function test_guest_cannot_update_parameter_values(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->logoutForTest();

        $this->patchJson($this->url($value), [
            'name' => 'Unauthorized',
        ])->assertUnauthorized();

        $this->assertSame('Existing Value', $value->fresh()->name);
    }

    public function test_user_without_update_permission_cannot_update(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->loginAs($this->createStaff());

        $this->patchJson($this->url($value), [
            'name' => 'Forbidden',
        ])->assertForbidden();

        $this->assertSame('Existing Value', $value->fresh()->name);
    }

    public function test_create_only_permission_does_not_allow_updates(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->staffRole->givePermissionTo('settings.create_parameter_value');
        $this->loginAs($this->createStaff());

        $this->patchJson($this->url($value), [
            'name' => 'Forbidden',
        ])->assertForbidden();
    }

    public function test_updated_by_identifies_the_actual_authorized_editor(): void
    {
        $value = $this->createValue($this->printingLocations);
        $editor = $this->createStaff();
        $this->staffRole->givePermissionTo('settings.update_parameter_value');
        $this->loginAs($editor);

        $this->patchJson($this->url($value), [
            'name' => 'Changed by Staff',
        ])->assertOk();

        $this->assertSame($this->administrator->id, $value->fresh()->created_by);
        $this->assertSame($editor->id, $value->fresh()->updated_by);
    }
}
