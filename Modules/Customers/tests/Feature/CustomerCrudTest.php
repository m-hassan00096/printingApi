<?php
namespace Modules\Customers\Tests\Feature;

require_once __DIR__.'/Support/CustomerTestCase.php';
require_once __DIR__.'/Support/FakeCustomerGuards.php';

use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerLifecycleRegistry;
use Modules\Customers\Tests\Feature\Support\CustomerTestCase;
use Modules\Customers\Tests\Feature\Support\FakeCustomerStatusTransitionGuard;
use Modules\Customers\Tests\Feature\Support\FakeCustomerUsageGuard;
use Modules\Settings\Enums\ParameterValueStatus;

class CustomerCrudTest extends CustomerTestCase
{
    public function test_create_returns_201_localized_message_and_created_customer_resource(): void
    {
        $response = $this->postJson('/api/v1/customers', $this->payload([
            'name' => '  Ahmed Ali  ',
            'phone' => '  01012345678  ',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('customers.messages.created'))
            ->assertJsonPath('data.name', 'Ahmed Ali')
            ->assertJsonPath('data.phone', '01012345678')
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.customerTypeId', $this->wholesale->id)
            ->assertJsonPath('data.customerType.id', $this->wholesale->id)
            ->assertJsonPath('data.customerType.name', 'Wholesale')
            ->assertJsonPath('data.status', 1)
            ->assertJsonPath('data.statusLabel', __('customers.status.active'));

        $createdId = $response->json('data.id');
        $this->assertIsInt($createdId);
        $this->assertGreaterThan(0, $createdId);
        $this->getJson('/api/v1/customers/'.$createdId)
            ->assertOk()->assertJsonPath('data.id', $createdId);

        $this->assertDatabaseHas('customers', [
            'id' => $createdId,
            'name' => 'Ahmed Ali',
            'phone' => '01012345678',
            'status' => 1,
            'customer_type_id' => $this->wholesale->id,
        ]);
    }

    public function test_update_is_partial_and_returns_localized_message_without_data(): void
    {
        $customer = $this->customer();

        $this->patchJson($this->url($customer), [
            'name' => '  Ahmed After  ',
            'status' => 0,
        ])->assertOk()
            ->assertJsonPath('message', __('customers.messages.updated'))
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Ahmed After',
            'phone' => '01012345678',
            'status' => 0,
        ]);

        $this->putJson($this->url($customer), [
            'address' => 'Mansoura',
            'customerTypeId' => $this->retail->id,
        ])->assertOk()->assertJsonPath('data', null);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'customer_type_id' => $this->retail->id,
            'address' => 'Mansoura',
        ]);
    }

    public function test_can_update_name_phone_and_customer_type_when_inactive(): void
    {
        $customer = $this->customer(['status' => CustomerStatus::INACTIVE]);

        $this->patchJson($this->url($customer), [
            'name' => 'Updated',
            'phone' => '01111111111',
            'customerTypeId' => $this->retail->id,
        ])->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated',
            'phone' => '01111111111',
            'status' => 0,
            'customer_type_id' => $this->retail->id,
        ]);
    }

    public function test_show_has_customer_type_and_status_label(): void
    {
        $customer = $this->customer(['name' => 'أحمد']);

        $this->getJson($this->url($customer))->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'أحمد')
            ->assertJsonPath('data.customerType.id', $this->wholesale->id)
            ->assertJsonPath('data.customerType.name', 'Wholesale')
            ->assertJsonPath('data.status', 1)
            ->assertJsonPath('data.statusLabel', __('customers.status.active'));
    }

    public function test_index_supports_name_or_phone_search_and_exact_type_status_filters(): void
    {
        $matched = $this->customer(['name' => 'Ahmed Ali', 'phone' => '01012345678']);
        $this->customer(['name' => 'Hassan', 'phone' => '01111111111', 'customer_type_id' => $this->retail->id, 'status' => 0]);

        $this->getJson('/api/v1/customers?filter[search]=Ahmed&filter[customerTypeId]='.$this->wholesale->id.'&filter[status]=1')
            ->assertOk()->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.customers.0.id', $matched->id);

        $this->getJson('/api/v1/customers?filter[search]=0101234')
            ->assertOk()->assertJsonPath('data.pagination.total', 1);

        $this->getJson('/api/v1/customers?filter[status]=0')
            ->assertOk()->assertJsonPath('data.pagination.total', 1);

        $this->getJson('/api/v1/customers?filter[search]=Wholesale')
            ->assertOk()->assertJsonPath('data.pagination.total', 0);
    }

    public function test_index_escapes_sql_like_wildcards_and_rejects_unknown_filters(): void
    {
        $this->customer(['name' => 'A%Brand']);

        $this->getJson('/api/v1/customers?filter[search]=%25')
            ->assertOk()->assertJsonPath('data.pagination.total', 1);

        $this->getJson('/api/v1/customers?filter[unsupported]=2')->assertUnprocessable();
        $this->getJson('/api/v1/customers?filter[status]=4')->assertUnprocessable();
    }

    public function test_duplicate_customer_names_are_permitted(): void
    {
        $this->customer();

        $this->postJson('/api/v1/customers', $this->payload([
            'phone' => '01000000002',
        ]))->assertCreated();

        $this->assertSame(2, Customer::query()->where('name', 'Ahmed')->count());
    }

    public function test_phone_is_unique_after_trimming_only(): void
    {
        $this->customer();

        $this->postJson('/api/v1/customers', $this->payload([
            'name' => 'Other',
            'phone' => '  01012345678   ',
        ]))->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->postJson('/api/v1/customers', $this->payload([
            'name' => 'Other',
            'phone' => '010 12345678',
        ]))->assertCreated();

        $this->assertDatabaseCount('customers', 2);
    }

    public function test_create_and_update_require_valid_active_customer_type(): void
    {
        $this->postJson('/api/v1/customers', $this->payload([
            'customerTypeId' => $this->printingLocation->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('customerTypeId');

        $this->retail->update(['status' => ParameterValueStatus::INACTIVE]);

        $this->postJson('/api/v1/customers', $this->payload([
            'customerTypeId' => $this->retail->id,
        ]))->assertUnprocessable();

        $customer = $this->customer();
        $this->patchJson($this->url($customer), [
            'customerTypeId' => $this->retail->id,
            'name' => 'Should rollback',
        ])->assertUnprocessable();

        $this->assertSame('Ahmed', $customer->fresh()->name);
        $this->assertSame($this->wholesale->id, $customer->fresh()->customer_type_id);
    }

    public function test_required_fields_are_rejected(): void
    {
        $this->postJson('/api/v1/customers', [
            'name' => ' ',
            'phone' => '',
            'status' => 9,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'customerTypeId', 'status']);
    }

    public function test_deletion_soft_deletes_and_releases_phone_for_reuse(): void
    {
        $customer = $this->customer();

        $this->deleteJson($this->url($customer))
            ->assertOk()->assertJsonPath('data', null);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'live_phone_hash' => null]);

        $this->getJson($this->url($customer))->assertNotFound();

        $this->postJson('/api/v1/customers', $this->payload([
            'name' => 'Reused',
        ]))->assertCreated();
    }

    public function test_registered_history_guard_blocks_customer_delete(): void
    {
        $customer = $this->customer(['name' => 'Has History']);
        app(CustomerLifecycleRegistry::class)->registerUsageGuard(FakeCustomerUsageGuard::class);

        $this->deleteJson($this->url($customer))->assertStatus(409)
            ->assertJsonPath('message', __('customers.messages.customer_in_use'));

        $this->assertNotNull($customer->fresh()->live_phone_hash);
    }

    public function test_registered_orders_guard_blocks_deactivation_but_not_other_edits(): void
    {
        $customer = $this->customer(['name' => 'Has Open Order']);
        app(CustomerLifecycleRegistry::class)->registerDeactivationGuard(FakeCustomerStatusTransitionGuard::class);

        $this->patchJson($this->url($customer), ['name' => 'Has Open Order', 'status' => 0])
            ->assertStatus(409)
            ->assertJsonPath('message', __('customers.messages.undelivered_orders'));

        $this->assertSame(CustomerStatus::ACTIVE, $customer->fresh()->status);

        $this->patchJson($this->url($customer), ['phone' => '2222'])
            ->assertOk()->assertJsonPath('data', null);
    }
}
