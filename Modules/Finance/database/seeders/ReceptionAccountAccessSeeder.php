<?php

namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Explicit opt-in: enable account review for existing reception collectors.
 * Does not create users, replace role permission sets, or grant refunds/write-offs.
 * Run after operational staff provisioning, not as part of a schema migration.
 */
class ReceptionAccountAccessSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', 'reception')->where('guard_name', 'web')->first();
        $permission = Permission::query()->where('name', 'finance.ledger.view')->where('guard_name', 'web')->first();
        if ($role === null || $permission === null) return;
        if (! $role->hasPermissionTo('finance.balances.view') || ! $role->hasPermissionTo('finance.collections.create')) return;
        $role->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
