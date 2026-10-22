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
            'settings.selects.view' => 'عرض قوائم اختيارات الإعدادات',
            'settings.view_parameter_values' => 'عرض قيم الإعدادات',
            'settings.create_parameter_value' => 'إضافة قيمة إعداد',
            'settings.update_parameter_value' => 'تعديل قيمة إعداد',
            'settings.delete_parameter_value' => 'حذف قيمة إعداد',
            'settings.bank_accounts.view' => 'عرض الحسابات البنكية',
            'settings.bank_accounts.create' => 'إضافة حساب بنكي',
            'settings.bank_accounts.update' => 'تعديل حساب بنكي',
            'settings.bank_accounts.delete' => 'حذف حساب بنكي',
            'settings.e_wallets.view' => 'عرض المحافظ الإلكترونية',
            'settings.e_wallets.create' => 'إضافة محفظة إلكترونية',
            'settings.e_wallets.update' => 'تعديل محفظة إلكترونية',
            'settings.e_wallets.delete' => 'حذف محفظة إلكترونية',
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
