<?php

namespace Modules\Identity\Actions\Roles;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Exceptions\Roles\ProtectedRoleException;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\RolePermissionService;

class UpdateRoleAction
{
    public function __construct(private readonly RolePermissionService $permissions) {}

    public function execute(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $role = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($role->guard_name === 'web', 404);

            // The technical `admin` role can only have its display alias changed.
            // No status deactivation or permission-set changes through this API.
            if ($role->name === 'admin' && (
                array_key_exists('permissionIds', $data)
                || (array_key_exists('status', $data) && (int) $data['status'] !== RoleStatus::ACTIVE->value)
            )) {
                throw new ProtectedRoleException();
            }

            if (array_key_exists('nameAr', $data)) {
                $role->translations()->updateOrCreate(
                    ['locale' => 'ar'],
                    ['alias' => $data['nameAr']]
                );
            }

            if (array_key_exists('status', $data)) {
                $role->update(['status' => $data['status']]);
            }

            if (array_key_exists('permissionIds', $data)) {
                $role->syncPermissions($this->permissions->resolveIds($data['permissionIds']));
            }

            return $role->load('translations', 'permissions')->loadCount('users');
        });
    }
}
