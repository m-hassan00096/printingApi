<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Explicit, idempotent staff setup for real environments.
 *
 * Run this seeder separately after the module permission seeders. The initial
 * password must be supplied as TABAA_STAFF_INITIAL_PASSWORD at runtime;
 * never put a shared staff password in a public repository.
 *
 * Existing users keep their password, name, status, and account details.
 * Existing users with an unexpected role are rejected rather than reassigned.
 */
class OperationalStaffSeeder extends Seeder
{
    public const DESIGNER_PERMISSIONS = [
        'production.items.list',
        'production.summary.view',
        'production.history.view',
        'production.design.start',
        'production.design.complete',
        'production.printing.send',
        'production.printing.change_location',
        'production.printing.complete',
        'production.items.undo',
        'customers.selects.view',
        'printing_services.selects.view',
    ];

    public const RECEPTION_PERMISSIONS = [
        'customers.view',
        'customers.create',
        'customers.update',
        'orders.list',
        'orders.view',
        'deliveries.ready.view',
        'deliveries.list',
        'deliveries.view',
        'deliveries.create',
        'finance.collections.view',
        'finance.collections.create',
        'finance.collections.reverse',
        'finance.collections.correct',
    ];

    public function run(): void
    {
        $password = (string) (getenv('TABAA_STAFF_INITIAL_PASSWORD') ?: '');

        if (strlen($password) < 8) {
            throw new RuntimeException(
                'Set TABAA_STAFF_INITIAL_PASSWORD to an initial password with at least eight characters.'
            );
        }

        $required = array_values(array_unique(array_merge(
            self::DESIGNER_PERMISSIONS,
            self::RECEPTION_PERMISSIONS,
        )));

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $required)
            ->get()
            ->keyBy('name');

        $missing = array_values(array_diff($required, $permissions->keys()->all()));

        if ($missing !== []) {
            throw new RuntimeException(
                'Seed the module permissions before OperationalStaffSeeder. Missing: '.implode(', ', $missing)
            );
        }

        DB::transaction(function () use ($password, $permissions): void {
            $roles = [];

            foreach ([
                'designers' => [
                    'المصممين',
                    'التصميم والطباعة وتجهيز العناصر للتسليم دون تسليم فعلي',
                    self::DESIGNER_PERMISSIONS,
                ],
                'reception' => [
                    'الاستقبال',
                    'إدارة بيانات العملاء والتسليمات وإيصالات التحصيل مع عرض الأوردرات',
                    self::RECEPTION_PERMISSIONS,
                ],
            ] as $roleName => [$alias, $description, $grants]) {
                $role = Role::query()->firstOrCreate(
                    ['name' => $roleName, 'guard_name' => 'web'],
                    ['status' => RoleStatus::ACTIVE],
                );

                $role->status = RoleStatus::ACTIVE;
                $role->save();

                $role->translations()->updateOrCreate(
                    ['locale' => 'ar'],
                    ['alias' => $alias, 'description' => $description],
                );

                // The role's permission set is intentionally authoritative.
                // Existing users with this role also inherit these permissions.
                $role->syncPermissions(
                    array_map(fn (string $key) => $permissions[$key], $grants)
                );

                $roles[$roleName] = $role;
            }

            foreach ([
                'user1' => ['مصمم 1', 'designers'],
                'user2' => ['مصمم 2', 'designers'],
                'user3' => ['موظف استقبال', 'reception'],
            ] as $username => [$name, $roleName]) {
                $user = User::withTrashed()->where('username', $username)->first();

                if ($user === null) {
                    $user = User::create([
                        'username' => $username,
                        'name' => $name,
                        'password' => $password,
                        'status' => UserStatus::ACTIVE,
                    ]);
                } else {
                    if ($user->trashed()) {
                        throw new RuntimeException(
                            "The username {$username} belongs to a soft-deleted account; resolve it manually."
                        );
                    }

                    $assignedRoles = $user->getRoleNames()->all();

                    if ($assignedRoles !== [$roleName]) {
                        throw new RuntimeException(
                            "The username {$username} already exists with another role; resolve it manually."
                        );
                    }
                }

                $user->syncRoles([$roles[$roleName]]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
