<?php
namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class OrderSelectsAccessTest extends OrdersTestCase
{
    public function test_orders_creator_can_fetch_active_customers_and_services_without_catalog_management_access(): void
    {
        $staff = User::create([
            'name' => 'Orders Operator', 'username' => 'orders_creator_selects',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $this->staffRole->syncPermissions([Permission::findOrCreate('orders.create', 'web')]);
        $staff->assignRole($this->staffRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->login($staff);

        $this->getJson('/api/v1/selects?selects=activeCustomers,printingServices')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'activeCustomers')
            ->assertJsonPath('data.1.label', 'printingServices')
            ->assertJsonPath('data.0.options.0.value', $this->customer->id)
            ->assertJsonPath('data.1.options.0.value', $this->service->id);

        // Viewing unrelated configuration remains restricted.
        $this->getJson('/api/v1/selects?selects=printingLocations')->assertForbidden();
        $this->getJson('/api/v1/selects?selects=bankAccounts')->assertForbidden();
    }

    public function test_confirmation_only_staff_can_read_payment_destinations_but_not_other_settings_selects(): void
    {
        $staff = User::create([
            'name' => 'Cashier', 'username' => 'cashier_order_selects',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $this->staffRole->syncPermissions([Permission::findOrCreate('orders.confirm', 'web')]);
        $staff->assignRole($this->staffRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->login($staff);

        $this->getJson('/api/v1/selects?selects=bankAccounts,eWallets')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'bankAccounts')
            ->assertJsonPath('data.1.label', 'eWallets');

        $this->getJson('/api/v1/selects?selects=printingLocations')->assertForbidden();
        $this->getJson('/api/v1/selects?selects=activeCustomers')->assertForbidden();
    }
}
