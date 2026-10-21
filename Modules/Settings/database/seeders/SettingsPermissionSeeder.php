<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SettingsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'settings.printing_locations.create' => 'إضافة مكان طباعة',
            'settings.customer_types.create' => 'إضافة نوع عميل',
        ];

        foreach ($permissions as $name => $alias) {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            $permission->translations()->updateOrCreate(
                ['locale' => 'ar'],
                ['alias' => $alias],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
