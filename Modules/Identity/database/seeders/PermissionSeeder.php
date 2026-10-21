<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'identity.users.view' => 'عرض المستخدمين',
            'identity.users.create' => 'إضافة مستخدم',
            'identity.users.update' => 'تعديل مستخدم',
            'identity.users.delete' => 'حذف مستخدم',

            'identity.roles.view' => 'عرض الأدوار',
            'identity.roles.create' => 'إضافة دور',
            'identity.roles.update' => 'تعديل دور',
            'identity.roles.delete' => 'حذف دور',

            'identity.permissions.view' => 'عرض الصلاحيات',
        ];

        foreach ($permissions as $name => $alias) {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            $permission->translations()->updateOrCreate(
                ['locale' => 'ar'],
                ['alias' => $alias]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
