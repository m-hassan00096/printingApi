<?php

namespace Modules\Settings\Tests\Feature\Selects;

require_once __DIR__.'/../ParameterValues/Support/ParameterValueTestCase.php';

use Modules\Identity\Models\Permission;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class SettingsSelectEndpointTest extends ParameterValueTestCase
{
    private function allowSettingsSelects(): void
    {
        $this->staffRole->givePermissionTo(
            Permission::findOrCreate('settings.selects.view', 'web'),
        );

        $this->loginAs($this->createStaff());
    }

    private function selectsUrl(string $keys, array $params = []): string
    {
        return '/api/v1/selects?'.http_build_query(
            array_replace(['selects' => $keys], $params),
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }

    public function test_endpoint_returns_both_settings_selects_in_requested_order(): void
    {
        $printing = $this->createValue($this->printingLocations, 'Main Print House');
        $customer = $this->createValue($this->customerTypes, 'Company');
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('printingLocations,customerTypes'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'printingLocations')
            ->assertJsonPath('data.0.options.0.value', $printing->id)
            ->assertJsonPath('data.0.options.0.label', 'Main Print House')
            ->assertJsonPath('data.1.label', 'customerTypes')
            ->assertJsonPath('data.1.options.0.value', $customer->id)
            ->assertJsonPath('data.1.options.0.label', 'Company');
    }

    public function test_endpoint_preserves_requested_key_order(): void
    {
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('customerTypes,printingLocations'))
            ->assertOk()
            ->assertJsonPath('data.0.label', 'customerTypes')
            ->assertJsonPath('data.1.label', 'printingLocations');
    }

    public function test_endpoint_excludes_inactive_and_soft_deleted_values(): void
    {
        $visible = $this->createValue($this->printingLocations, 'Available');
        $this->createValue($this->printingLocations, 'Inactive', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);
        $deleted = $this->createValue($this->printingLocations, 'Deleted');
        $deleted->delete();

        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('printingLocations'))
            ->assertOk()
            ->assertJsonCount(1, 'data.0.options')
            ->assertJsonPath('data.0.options.0.value', $visible->id);
    }

    public function test_endpoint_accepts_active_filter_for_both_keys(): void
    {
        $printing = $this->createValue($this->printingLocations, 'Printed');
        $customer = $this->createValue($this->customerTypes, 'Corporate');
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('printingLocations,customerTypes', [
            'params' => [
                'printingLocations' => 'active',
                'customerTypes' => 'active',
            ],
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.options.0.value', $printing->id)
            ->assertJsonPath('data.1.options.0.value', $customer->id);
    }

    public function test_endpoint_rejects_unsupported_settings_filter(): void
    {
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('printingLocations', [
            'params' => ['printingLocations' => 'inactive'],
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_endpoint_rejects_unknown_select_key(): void
    {
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('unknownSettingsSelect'))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_endpoint_rejects_parameters_for_unrequested_selects(): void
    {
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('printingLocations', [
            'params' => ['customerTypes' => 'active'],
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_guest_cannot_access_settings_selects(): void
    {
        $this->logoutForTest();

        $this->getJson($this->selectsUrl('printingLocations'))
            ->assertUnauthorized();
    }

    public function test_user_without_settings_selects_permission_is_forbidden(): void
    {
        $this->loginAs($this->createStaff());

        $this->getJson($this->selectsUrl('printingLocations'))
            ->assertForbidden();

        $this->getJson($this->selectsUrl('customerTypes'))
            ->assertForbidden();
    }

    public function test_settings_management_view_permission_does_not_grant_select_access(): void
    {
        $this->staffRole->givePermissionTo('settings.view_parameter_values');
        $this->loginAs($this->createStaff());

        $this->getJson($this->selectsUrl('printingLocations'))
            ->assertForbidden();
    }

    public function test_settings_selects_permission_does_not_grant_management_access(): void
    {
        $value = $this->createValue($this->printingLocations, 'Visible Select');
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('printingLocations'))
            ->assertOk()
            ->assertJsonPath('data.0.options.0.value', $value->id);

        $this->getJson('/api/v1/settings/parameter-values?parameterId='.$this->printingLocations->id)
            ->assertForbidden();
    }

    public function test_mixed_selects_do_not_reveal_data_if_another_key_is_unauthorized(): void
    {
        $this->createValue($this->printingLocations, 'Private Print House');
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('printingLocations,users'))
            ->assertForbidden()
            ->assertDontSee('Private Print House');
    }

    public function test_identity_and_settings_selects_can_share_the_same_request(): void
    {
        $printing = $this->createValue($this->printingLocations, 'Office Printing');
        $customer = $this->createValue($this->customerTypes, 'Company');
        $this->staffRole->givePermissionTo(
            Permission::findOrCreate('identity.users.view', 'web'),
        );
        $this->allowSettingsSelects();

        $this->getJson($this->selectsUrl('users,printingLocations,customerTypes'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.label', 'users')
            ->assertJsonPath('data.1.label', 'printingLocations')
            ->assertJsonPath('data.1.options.0.value', $printing->id)
            ->assertJsonPath('data.2.label', 'customerTypes')
            ->assertJsonPath('data.2.options.0.value', $customer->id);
    }
}
