<?php
namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class FinancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'balances.view', 'ledger.view', 'opening_balances.create',
            'collections.view', 'collections.create', 'collections.reverse',
            'refunds.view', 'refunds.create',
        ] as $name) {
            $permission = Permission::firstOrCreate(['name' => 'finance.'.$name, 'guard_name' => 'web']);
            $permission->translations()->updateOrCreate(
                ['locale' => 'ar'], ['alias' => trans('finance.permissions.'.$name, [], 'ar')],
            );
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
