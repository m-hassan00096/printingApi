<?php
namespace Modules\Deliveries\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DeliveriesPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ready.view', 'list', 'view', 'create', 'cancel'] as $p) {
            $permission = Permission::firstOrCreate(['name' => 'deliveries.'.$p, 'guard_name' => 'web']);
            $permission->translations()->updateOrCreate(['locale' => 'ar'],
                ['alias' => trans('deliveries.permissions.'.$p, [], 'ar')]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
