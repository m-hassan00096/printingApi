<?php

namespace Modules\Identity\Services;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Exceptions\Users\LastActiveAdminException;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Support\Config;

/**
 * Run inside the same database transaction that modifies a user's role/status.
 * Locking the admin role serializes concurrent operations which could each
 * otherwise remove one of the remaining active admin accounts.
 */
class LastActiveAdminGuard
{
    public function assertCanDelete(User $user): void
    {
        $this->assertCanRemoveAccess($user, true);
    }

    public function assertCanUpdate(User $user, array $data): void
    {
        if (! array_key_exists('status', $data) && ! array_key_exists('roleId', $data)) {
            return;
        }

        $admin = $this->lockAdminRole();
        if (! $admin) {
            return;
        }

        $deactivating = array_key_exists('status', $data)
            && (int) $data['status'] !== UserStatus::ACTIVE->value;
        $demoting = array_key_exists('roleId', $data)
            && (int) $data['roleId'] !== $admin->getKey();

        if ($deactivating || $demoting) {
            $this->assertActiveAdminWillRemain($user, $admin);
        }
    }

    private function assertCanRemoveAccess(User $user, bool $isRemoval): void
    {
        if (! $isRemoval) {
            return;
        }

        $admin = $this->lockAdminRole();
        if ($admin) {
            $this->assertActiveAdminWillRemain($user, $admin);
        }
    }

    private function lockAdminRole(): ?Role
    {
        return Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->first();
    }

    private function assertActiveAdminWillRemain(User $user, Role $admin): void
    {
        if ($user->status !== UserStatus::ACTIVE) {
            return;
        }

        $pivot = Config::modelHasRolesTable();
        $roleColumn = app(PermissionRegistrar::class)->pivotRole;
        $modelColumn = Config::morphKey();

        $isAdmin = DB::table($pivot)
            ->where($roleColumn, $admin->getKey())
            ->where('model_type', $user->getMorphClass())
            ->where($modelColumn, $user->getKey())
            ->exists();

        if (! $isAdmin) {
            return;
        }

        $activeAdmins = DB::table($pivot.' as assignments')
            ->join('users', 'users.id', '=', 'assignments.'.$modelColumn)
            ->where('assignments.'.$roleColumn, $admin->getKey())
            ->where('assignments.model_type', $user->getMorphClass())
            ->where('users.status', UserStatus::ACTIVE->value)
            ->whereNull('users.deleted_at')
            ->count();

        if ($activeAdmins <= 1) {
            throw new LastActiveAdminException();
        }
    }
}
