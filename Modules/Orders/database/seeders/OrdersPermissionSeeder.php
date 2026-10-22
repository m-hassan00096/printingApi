<?php
namespace Modules\Orders\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class OrdersPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['view', 'create', 'update', 'delete', 'confirm', 'cancel'] as $action) {
            $permission = Permission::firstOrCreate([
                'name' => 'orders.'.$action,
                'guard_name' => 'web',
            ]);

            $permission->translations()->updateOrCreate(
                ['locale' => 'ar'],
                ['alias' => trans('orders.permissions.'.$action, [], 'ar')],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
