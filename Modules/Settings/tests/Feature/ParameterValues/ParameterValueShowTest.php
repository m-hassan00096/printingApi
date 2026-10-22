<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class ParameterValueShowTest extends ParameterValueTestCase
{
    public function test_can_show_parameter_value_details(): void
    {
        $value = $this->createValue($this->customerTypes, 'Corporate', [
            'description' => 'Corporate customers',
            'status' => ParameterValueStatus::INACTIVE,
        ]);

        $this->getJson($this->url($value))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $value->id)
            ->assertJsonPath('data.parameterId', $this->customerTypes->id)
            ->assertJsonPath('data.name', 'Corporate')
            ->assertJsonPath('data.description', 'Corporate customers')
            ->assertJsonPath('data.status', 0);
    }

    public function test_unknown_value_returns_not_found(): void
    {
        $this->getJson($this->url().'/999999')->assertNotFound();
    }

    public function test_soft_deleted_value_returns_not_found(): void
    {
        $value = $this->createValue($this->printingLocations);
        $value->delete();

        $this->getJson($this->url($value))->assertNotFound();
    }

    public function test_guest_cannot_show_parameter_value(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->logoutForTest();

        $this->getJson($this->url($value))->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_show_parameter_value(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->loginAs($this->createStaff());

        $this->getJson($this->url($value))->assertForbidden();
    }

    public function test_user_with_view_permission_can_show_value(): void
    {
        $value = $this->createValue($this->printingLocations);
        $this->staffRole->givePermissionTo('settings.view_parameter_values');
        $this->loginAs($this->createStaff());

        $this->getJson($this->url($value))
            ->assertOk()
            ->assertJsonPath('data.id', $value->id);
    }
}
