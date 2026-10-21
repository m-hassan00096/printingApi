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
            'settings.view_parameter_values' => 'عرض قيم الإعدادات',
            'settings.create_parameter_value' => 'إضافة قيمة إعداد',
            'settings.update_parameter_value' => 'تعديل قيمة إعداد',
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
