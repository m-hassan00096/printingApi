<?php

namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Explicit opt-in: enable account review for existing reception collectors.
 * Does not create users or replace role permission sets. Run explicitly when
 * the business authorizes reception to apply audited settlement discounts.
 * Run after operational staff provisioning, not as part of a schema migration.
 */
class ReceptionAccountAccessSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', 'reception')->where('guard_name', 'web')->first();
        if ($role === null) return;
        if (! $role->hasPermissionTo('finance.balances.view') || ! $role->hasPermissionTo('finance.collections.create')) return;
        foreach (['finance.ledger.view', 'finance.settlements.discount'] as $name) {
            $permission = Permission::query()->where('name', $name)->where('guard_name', 'web')->first();
            if ($permission !== null) $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
