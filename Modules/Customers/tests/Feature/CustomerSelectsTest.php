<?php
namespace Modules\Customers\Tests\Feature;

require_once __DIR__.'/Support/CustomerTestCase.php';

use Modules\Customers\Enums\CustomerStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Identity\Enums\UserStatus;
use Modules\Customers\Tests\Feature\Support\CustomerTestCase;

class CustomerSelectsTest extends CustomerTestCase
{
    public function test_both_selects_include_phone_and_have_distinct_status_scopes(): void
    {
        $live = $this->customer(['name' => 'Ahmed', 'phone' => '01012345678']);
        $this->customer(['name' => 'Mona', 'phone' => '01111111111', 'status' => CustomerStatus::INACTIVE]);
        $deleted = $this->customer(['name' => 'Deleted', 'phone' => '01222222222']);
        $deleted->delete();

        $this->getJson('/api/v1/selects?selects=customers,activeCustomers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'customers')
            ->assertJsonCount(2, 'data.0.options')
            ->assertJsonPath('data.0.options.0.label', 'Ahmed - 01012345678')
            ->assertJsonPath('data.1.label', 'activeCustomers')
            ->assertJsonCount(1, 'data.1.options')
            ->assertJsonPath('data.1.options.0.value', $live->id);
    }

    public function test_unsupported_select_parameter_is_rejected(): void
    {
        $this->getJson('/api/v1/selects?selects=customers&params[customers]=inactive')
            ->assertUnprocessable();

        $this->getJson('/api/v1/selects?selects=activeCustomers&params[activeCustomers]=active')
            ->assertOk();
    }
    public function test_finance_ledger_only_staff_can_select_customers_but_not_manage_them(): void
    {
        $this->customer(['name' => 'Account Payer', 'phone' => '01211111111', 'status' => CustomerStatus::INACTIVE]);
        $this->staffRole->givePermissionTo(Permission::findOrCreate('finance.ledger.view', 'web'));
        $staff = User::create([
            'name' => 'Accounts Clerk', 'username' => 'accounts_clerk',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);

        $this->getJson('/api/v1/selects?selects=customers')->assertOk()
            ->assertJsonPath('data.0.options.0.label', 'Account Payer - 01211111111');
        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->getJson('/api/v1/selects?selects=activeCustomers')->assertForbidden();
    }

    public function test_refund_only_staff_can_select_inactive_customers_without_customers_crud(): void
    {
        $this->customer(['name' => 'Credit Payer', 'phone' => '01233333333', 'status' => CustomerStatus::INACTIVE]);
        $this->staffRole->givePermissionTo(Permission::findOrCreate('finance.refunds.create', 'web'));
        $staff = User::create([
            'name' => 'Refund Clerk', 'username' => 'refund_clerk',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);

        $this->getJson('/api/v1/selects?selects=customers')->assertOk()
            ->assertJsonPath('data.0.options.0.label', 'Credit Payer - 01233333333');
        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->getJson('/api/v1/selects?selects=activeCustomers')->assertForbidden();
    }

    public function test_finance_collector_can_select_inactive_customer_without_customer_management_permissions(): void
    {
        $this->customer(['name' => 'Inactive Payer', 'phone' => '01234567890', 'status' => CustomerStatus::INACTIVE]);
        $this->staffRole->givePermissionTo(Permission::findOrCreate('finance.collections.create', 'web'));
        $collector = User::create([
            'name' => 'Collector', 'username' => 'finance_collector',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $collector->assignRole($this->staffRole);
        $this->login($collector);

        $this->getJson('/api/v1/selects?selects=customers')->assertOk()
            ->assertJsonPath('data.0.options.0.label', 'Inactive Payer - 01234567890');
        $this->getJson('/api/v1/selects?selects=activeCustomers')->assertForbidden();
        $this->getJson('/api/v1/customers')->assertForbidden();
    }

}
