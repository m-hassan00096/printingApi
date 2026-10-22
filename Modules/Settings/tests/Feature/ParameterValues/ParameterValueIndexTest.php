<?php

namespace Modules\Settings\Tests\Feature\ParameterValues;

require_once __DIR__.'/Support/ParameterValueTestCase.php';

use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class ParameterValueIndexTest extends ParameterValueTestCase
{
    private function indexUrl(array $params = []): string
    {
        return $this->url().'?'.http_build_query(
            array_replace(['parameterId' => $this->printingLocations->id], $params),
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }

    public function test_index_returns_parameters_key_and_pagination(): void
    {
        $this->createValue($this->printingLocations, 'Print House A');
        $this->createValue($this->printingLocations, 'Print House B');
        $this->createValue($this->customerTypes, 'Corporate Customer');

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.parameters')
            ->assertJsonMissingPath('data.users')
            ->assertJsonMissingPath('data.items')
            ->assertJsonPath('data.pagination.currentPage', 1)
            ->assertJsonPath('data.pagination.lastPage', 1)
            ->assertJsonPath('data.pagination.perPage', 15)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_parameter_id_is_required(): void
    {
        $this->getJson($this->url())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameterId');
    }

    public function test_parameter_id_must_reference_an_existing_group(): void
    {
        $this->getJson($this->indexUrl(['parameterId' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameterId');
    }

    public function test_different_groups_are_isolated(): void
    {
        $printing = $this->createValue($this->printingLocations, 'Printing Only');
        $customer = $this->createValue($this->customerTypes, 'Customer Only');

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.parameters.0.id', $printing->id);

        $this->getJson($this->indexUrl(['parameterId' => $this->customerTypes->id]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.parameters.0.id', $customer->id);
    }

    public function test_search_matches_name(): void
    {
        $match = $this->createValue($this->printingLocations, 'Premium Print House');
        $this->createValue($this->printingLocations, 'Other Location');

        $this->getJson($this->indexUrl(['filter' => ['search' => 'Premium']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.parameters.0.id', $match->id);
    }

    public function test_search_matches_description(): void
    {
        $match = $this->createValue($this->customerTypes, 'Business', [
            'description' => 'Corporate client',
        ]);
        $this->createValue($this->customerTypes, 'Individual', [
            'description' => 'Walk-in customer',
        ]);

        $this->getJson($this->indexUrl([
            'parameterId' => $this->customerTypes->id,
            'filter' => ['search' => 'Corporate'],
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.parameters.0.id', $match->id);
    }

    public function test_search_does_not_leak_matches_from_another_group(): void
    {
        $this->createValue($this->customerTypes, 'Premium Company', [
            'description' => 'Premium printing',
        ]);
        $this->createValue($this->printingLocations, 'Other Location');

        $this->getJson($this->indexUrl(['filter' => ['search' => 'Premium']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonCount(0, 'data.parameters');
    }

    public function test_status_filter_includes_zero_for_inactive_values(): void
    {
        $inactive = $this->createValue($this->printingLocations, 'Inactive Location', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);
        $this->createValue($this->printingLocations, 'Active Location');

        $this->getJson($this->indexUrl(['filter' => ['status' => 0]]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.parameters.0.id', $inactive->id)
            ->assertJsonPath('data.parameters.0.status', 0);
    }

    public function test_search_and_status_can_be_combined(): void
    {
        $match = $this->createValue($this->printingLocations, 'Target Inactive', [
            'description' => 'Priority',
            'status' => ParameterValueStatus::INACTIVE,
        ]);
        $this->createValue($this->printingLocations, 'Target Active');
        $this->createValue($this->printingLocations, 'Other Inactive', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);

        $this->getJson($this->indexUrl([
            'filter' => ['search' => 'Target', 'status' => 0],
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.parameters.0.id', $match->id);
    }

    public function test_pagination_uses_page_and_per_page(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createValue($this->printingLocations, 'Location '.$i);
        }

        $this->getJson($this->indexUrl(['page' => 2, 'perPage' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data.parameters')
            ->assertJsonPath('data.pagination.currentPage', 2)
            ->assertJsonPath('data.pagination.lastPage', 3)
            ->assertJsonPath('data.pagination.perPage', 2)
            ->assertJsonPath('data.pagination.total', 5);
    }

    public function test_default_sort_is_latest_id_first(): void
    {
        $this->createValue($this->printingLocations, 'First');
        $latest = $this->createValue($this->printingLocations, 'Second');

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonPath('data.parameters.0.id', $latest->id);
    }

    public function test_can_sort_by_name(): void
    {
        $this->createValue($this->printingLocations, 'Zulu');
        $first = $this->createValue($this->printingLocations, 'Alpha');

        $this->getJson($this->indexUrl(['sort' => 'name']))
            ->assertOk()
            ->assertJsonPath('data.parameters.0.id', $first->id);
    }

    public function test_soft_deleted_values_are_excluded(): void
    {
        $deleted = $this->createValue($this->printingLocations, 'Deleted');
        $deleted->delete();
        $active = $this->createValue($this->printingLocations, 'Visible');

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.parameters.0.id', $active->id);

        $this->assertSoftDeleted('parameter_values', ['id' => $deleted->id]);
    }

    public function test_disallowed_filter_is_rejected(): void
    {
        $this->getJson($this->indexUrl(['filter' => ['parameter_id' => 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    public function test_disallowed_sort_is_rejected(): void
    {
        $this->getJson($this->indexUrl(['sort' => 'description']))
            ->assertStatus(400);
    }

    public function test_per_page_cannot_exceed_one_hundred(): void
    {
        $this->getJson($this->indexUrl(['perPage' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('perPage');
    }

    public function test_guest_cannot_list_parameter_values(): void
    {
        $this->logoutForTest();

        $this->getJson($this->indexUrl())->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_list(): void
    {
        $this->loginAs($this->createStaff());

        $this->getJson($this->indexUrl())->assertForbidden();
    }

    public function test_create_permission_does_not_grant_view_permission(): void
    {
        $this->staffRole->givePermissionTo('settings.create_parameter_value');
        $this->loginAs($this->createStaff());

        $this->getJson($this->indexUrl())->assertForbidden();
    }

    public function test_view_permission_allows_listing(): void
    {
        $value = $this->createValue($this->printingLocations, 'Allowed');
        $this->staffRole->givePermissionTo('settings.view_parameter_values');
        $this->loginAs($this->createStaff());

        $this->getJson($this->indexUrl())
            ->assertOk()
            ->assertJsonPath('data.parameters.0.id', $value->id);

        $this->postJson($this->url(), $this->payload())->assertForbidden();
        $this->patchJson($this->url($value), ['name' => 'Denied'])->assertForbidden();
    }
}
