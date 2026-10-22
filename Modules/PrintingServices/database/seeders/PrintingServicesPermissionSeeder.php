<?php

namespace Modules\PrintingServices\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PrintingServicesPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'printing_services.view' => 'عرض خدمات الطباعة',
            'printing_services.create' => 'إضافة خدمة طباعة',
            'printing_services.update' => 'تعديل خدمات الطباعة وشرائح الأسعار',
            'printing_services.delete' => 'حذف خدمة طباعة',
            'printing_services.selects.view' => 'عرض قائمة اختيار خدمات الطباعة',
            'printing_services.price_preview.view' => 'عرض السعر المقترح لخدمة الطباعة',
        ];

        foreach ($permissions as $name => $alias) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $permission->translations()->updateOrCreate(
                ['locale' => 'ar'],
                ['alias' => $alias],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

