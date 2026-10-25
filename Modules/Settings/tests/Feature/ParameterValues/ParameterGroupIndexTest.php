<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class ParameterGroupIndexTest extends ParameterValueTestCase
{
    private const URL = '/api/v1/settings/parameter-groups';

    public function test_authorized_user_receives_real_group_ids_and_known_group_names(): void
    {
        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.groups')
            ->assertJsonPath('data.groups.0.name', 'customer_types')
            ->assertJsonPath('data.groups.0.id', $this->customerTypes->id)
            ->assertJsonPath('data.groups.1.name', 'printing_locations')
            ->assertJsonPath('data.groups.1.id', $this->printingLocations->id);
    }

    public function test_guests_cannot_discover_management_group_ids(): void
    {
        $this->logoutForTest();
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_select_only_permission_does_not_grant_access(): void
    {
        $this->staffRole->givePermissionTo('settings.selects.view');
        $this->loginAs($this->createStaff());
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_view_permission_can_read_groups_without_write_access(): void
    {
        $this->staffRole->givePermissionTo('settings.view_parameter_values');
        $this->loginAs($this->createStaff());
        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(2, 'data.groups');
        $this->postJson($this->url(), $this->payload())->assertForbidden();
    }
}
