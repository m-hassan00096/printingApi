<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Modules\Settings\Actions\ParameterValues\DeleteParameterValueAction;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class ParameterValueDeleteTest extends ParameterValueTestCase
{
    public function test_authorized_user_can_soft_delete_a_value(): void
    {
        $value = $this->createValue($this->printingLocations, 'Printing House');

        $this->deleteJson($this->url($value))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('parameter_values', ['id' => $value->id]);
        $this->assertDatabaseCount('parameter_values', 1);
        $this->assertNull(ParameterValue::query()->find($value->id));
        $this->assertSame('Printing House', ParameterValue::withTrashed()->findOrFail($value->id)->name);
    }

    public function test_inactive_value_can_be_soft_deleted(): void
    {
        $value = $this->createValue($this->customerTypes, 'Inactive Type', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);

        $this->deleteJson($this->url($value))->assertOk();

        $this->assertSoftDeleted('parameter_values', ['id' => $value->id]);
    }

    public function test_deleted_value_is_not_available_in_show_or_index(): void
    {
        $value = $this->createValue($this->printingLocations, 'Removed');

        $this->deleteJson($this->url($value))->assertOk();

        $this->getJson($this->url($value))->assertNotFound();
        $this->getJson($this->url().'?parameterId='.$this->printingLocations->id)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonCount(0, 'data.parameters');
    }

    public function test_unknown_value_cannot_be_deleted(): void
    {
        $this->deleteJson($this->url().'/999999')->assertNotFound();
    }

    public function test_action_rejects_a_stale_model_that_was_already_soft_deleted(): void
    {
        $value = $this->createValue($this->printingLocations, 'Previously Deleted');

        // Mimic a second request that already resolved this record.
        $stale = ParameterValue::findOrFail($value->id);

        $this->deleteJson($this->url($value))->assertOk();

        $this->expectException(ModelNotFoundException::class);

        app(DeleteParameterValueAction::class)->execute($stale);
    }

    public function test_already_deleted_value_cannot_be_deleted_again(): void
    {
        $value = $this->createValue($this->printingLocations);
        $value->delete();

        $this->deleteJson($this->url($value))->assertNotFound();
    }

    public function test_guest_cannot_delete_a_value(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->logoutForTest();

        $this->deleteJson($this->url($value))->assertUnauthorized();

        $this->assertNotSoftDeleted('parameter_values', ['id' => $value->id]);
    }

    public function test_user_without_delete_permission_cannot_delete_a_value(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->loginAs($this->createStaff());

        $this->deleteJson($this->url($value))->assertForbidden();

        $this->assertNotSoftDeleted('parameter_values', ['id' => $value->id]);
    }

    public function test_view_permission_does_not_grant_delete_permission(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->staffRole->givePermissionTo('settings.view_parameter_values');
        $this->loginAs($this->createStaff());

        $this->deleteJson($this->url($value))->assertForbidden();

        $this->assertNotSoftDeleted('parameter_values', ['id' => $value->id]);
    }

    public function test_create_permission_does_not_grant_delete_permission(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->staffRole->givePermissionTo('settings.create_parameter_value');
        $this->loginAs($this->createStaff());

        $this->deleteJson($this->url($value))->assertForbidden();
    }

    public function test_update_permission_does_not_grant_delete_permission(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->staffRole->givePermissionTo('settings.update_parameter_value');
        $this->loginAs($this->createStaff());

        $this->deleteJson($this->url($value))->assertForbidden();
    }

    public function test_delete_permission_alone_allows_only_deletion(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->staffRole->givePermissionTo('settings.delete_parameter_value');
        $this->loginAs($this->createStaff());

        $this->postJson($this->url(), $this->payload())->assertForbidden();
        $this->getJson($this->url().'?parameterId='.$this->printingLocations->id)->assertForbidden();
        $this->patchJson($this->url($value), ['name' => 'Updated'])->assertForbidden();

        $this->deleteJson($this->url($value))->assertOk();
    }

    public function test_deleted_name_is_still_reserved_within_the_same_group(): void
    {
        $value = $this->createValue($this->printingLocations, 'Reserved Name');

        $this->deleteJson($this->url($value))->assertOk();

        $this->postJson($this->url(), $this->payload([
            'name' => 'Reserved Name',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('parameter_values', 1);
    }

    public function test_deleted_name_can_be_used_in_another_group(): void
    {
        $value = $this->createValue($this->printingLocations, 'Shared Name');

        $this->deleteJson($this->url($value))->assertOk();

        $this->postJson($this->url(), $this->payload([
            'parameterId' => $this->customerTypes->id,
            'name' => 'Shared Name',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.parameterId', $this->customerTypes->id);

        $this->assertDatabaseCount('parameter_values', 2);
    }

    public function test_updating_another_value_to_a_deleted_name_is_rejected(): void
    {
        $deleted = $this->createValue($this->printingLocations, 'Previously Used');
        $remaining = $this->createValue($this->printingLocations, 'Current Name');

        $this->deleteJson($this->url($deleted))->assertOk();

        $this->patchJson($this->url($remaining), [
            'name' => 'Previously Used',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame('Current Name', $remaining->fresh()->name);
    }

    public function test_database_unique_constraint_protects_deleted_names_without_validation(): void
    {
        $value = $this->createValue($this->printingLocations, 'Reserved Database Name');
        $value->delete();

        $this->expectException(QueryException::class);

        $this->createValue($this->printingLocations, 'Reserved Database Name');
    }

    public function test_database_unique_constraint_rejects_duplicated_active_names(): void
    {
        $this->createValue($this->customerTypes, 'Duplicate Name');

        $this->expectException(QueryException::class);

        $this->createValue($this->customerTypes, 'Duplicate Name');
    }
}
