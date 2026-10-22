<?php
namespace Modules\Customers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CustomersPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['view', 'create', 'update', 'delete', 'selects.view'] as $name) {
            $permission = Permission::firstOrCreate([
                'name' => 'customers.'.$name,
                'guard_name' => 'web',
            ]);

            $permission->translations()->updateOrCreate(
                ['locale' => 'ar'],
                ['alias' => trans('customers.permissions.'.$name, [], 'ar')],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
