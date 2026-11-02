<?php

namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Modules\Finance\Database\Seeders\ReceptionAccountAccessSeeder;
use Modules\Finance\Database\Seeders\FinanceDatabaseSeeder;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class ReceptionAccountAccessTest extends OrdersTestCase
{
    public function test_finance_seeding_grants_admin_discount_permission_without_granting_reception(): void
    {
        $reception = Role::create(['name' => 'reception', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);
        $this->seed(FinanceDatabaseSeeder::class);
        $this->seed(FinanceDatabaseSeeder::class);

        $this->assertTrue($this->adminRole->fresh()->hasPermissionTo('finance.settlements.discount'));
        $this->assertFalse($reception->fresh()->hasPermissionTo('finance.settlements.discount'));
    }

    public function test_explicit_reception_upgrade_is_additive_and_does_not_grant_refunds(): void
    {
        foreach (['finance.balances.view', 'finance.ledger.view', 'finance.collections.create', 'finance.refunds.create', 'finance.settlements.discount'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::create(['name' => 'reception', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);
        $role->givePermissionTo(['orders.view', 'finance.balances.view', 'finance.collections.create']);
        $this->seed(ReceptionAccountAccessSeeder::class);
        $this->seed(ReceptionAccountAccessSeeder::class);
        $role = $role->fresh();
        $this->assertTrue($role->hasPermissionTo('finance.ledger.view'));
        $this->assertTrue($role->hasPermissionTo('finance.settlements.discount'));
        $this->assertTrue($role->hasPermissionTo('orders.view'));
        $this->assertTrue($role->hasPermissionTo('finance.collections.create'));
        $this->assertFalse($role->hasPermissionTo('finance.refunds.create'));
    }

    public function test_upgrade_does_not_broaden_a_reception_role_without_collection_access(): void
    {
        foreach (['finance.balances.view', 'finance.ledger.view', 'finance.collections.create', 'finance.settlements.discount'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::create(['name' => 'reception', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);
        $role->givePermissionTo('finance.balances.view');
        $this->seed(ReceptionAccountAccessSeeder::class);
        $this->assertFalse($role->fresh()->hasPermissionTo('finance.ledger.view'));
        $this->assertFalse($role->fresh()->hasPermissionTo('finance.collections.create'));
        $this->assertFalse($role->fresh()->hasPermissionTo('finance.settlements.discount'));
    }
}
