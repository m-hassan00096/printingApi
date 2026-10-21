<?php

namespace Modules\Identity\Actions\Roles;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\RoleNameGenerator;
use Modules\Identity\Services\RolePermissionService;

class CreateRoleAction
{
    public function __construct(
        private readonly RoleNameGenerator $names,
        private readonly RolePermissionService $permissions,
    ) {}

    public function execute(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $permissions = $this->permissions->resolveIds($data['permissionIds']);

            $role = Role::create([
                'name' => $this->names->generate($data['nameAr']),
                'guard_name' => 'web',
                'status' => $data['status'],
            ]);

            $role->translations()->create([
                'locale' => 'ar',
                'alias' => $data['nameAr'],
            ]);

            $role->syncPermissions($permissions);

            return $role->load('translations', 'permissions')->loadCount('users');
        });
    }
}
