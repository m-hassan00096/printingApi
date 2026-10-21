<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            [
                'name' => 'admin',
                'guard_name' => 'web',
            ],
            [
                'status' => RoleStatus::ACTIVE,
            ]
        );

        $role->translations()->updateOrCreate(
            ['locale' => 'ar'],
            [
                'alias' => 'مدير النظام',
                'description' => 'مسؤول عن إدارة النظام والمستخدمين والصلاحيات',
            ]
        );

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->get();

        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
