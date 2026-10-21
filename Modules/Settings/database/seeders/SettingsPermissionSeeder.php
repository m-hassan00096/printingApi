<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SettingsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'settings.create_parameter_value',
            'guard_name' => 'web',
        ]);

        $permission->translations()->updateOrCreate(
            ['locale' => 'ar'],
            ['alias' => 'إضافة قيمة إعداد'],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
