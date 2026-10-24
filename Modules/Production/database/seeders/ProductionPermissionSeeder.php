<?php
namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ProductionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'items.list', 'summary.view', 'history.view',
            'design.start', 'design.complete',
            'printing.send', 'printing.change_location', 'printing.complete', 'items.undo',
        ] as $action) {
            $p = Permission::firstOrCreate(['name' => 'production.'.$action, 'guard_name' => 'web']);
            $p->translations()->updateOrCreate(['locale' => 'ar'],
                ['alias' => trans('production.permissions.'.$action, [], 'ar')]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
