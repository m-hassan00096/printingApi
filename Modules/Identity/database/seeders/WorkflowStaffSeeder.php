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
 * Opt-in staff setup for local/demo environments; never part of DatabaseSeeder.
 * Supply the initial password via TABAA_WORKFLOW_STAFF_PASSWORD (not committed).
 * Existing passwords and user profile data are never overwritten on a re-run.
 */
class WorkflowStaffSeeder extends Seeder
{
    public const DESIGNER_PERMISSIONS = [
        'production.items.list', 'production.summary.view', 'production.history.view',
        'production.design.start', 'production.design.complete',
        'production.printing.send', 'production.printing.change_location',
        'production.printing.complete', 'production.items.undo',
        'customers.selects.view', 'printing_services.selects.view',
    ];

    public const RECEPTION_PERMISSIONS = [
        'customers.view',
        'orders.list', 'orders.view',
        'deliveries.ready.view', 'deliveries.list', 'deliveries.view',
        'finance.collections.view',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('WorkflowStaffSeeder is permitted only in local/testing environments.');
        }

        $password = (string) (getenv('TABAA_WORKFLOW_STAFF_PASSWORD') ?: '');
        if (strlen($password) < 8) {
            throw new RuntimeException('Set TABAA_WORKFLOW_STAFF_PASSWORD to the requested password before running this seeder.');
        }

        $required = array_values(array_unique(array_merge(self::DESIGNER_PERMISSIONS, self::RECEPTION_PERMISSIONS)));
        $permissions = Permission::query()->where('guard_name', 'web')
            ->whereIn('name', $required)->get()->keyBy('name');
        $missing = array_values(array_diff($required, $permissions->keys()->all()));
        if ($missing !== []) {
            throw new RuntimeException('Seed all module permissions first. Missing: '.implode(', ', $missing));
        }

        DB::transaction(function () use ($permissions, $password): void {
            $roles = [];
            foreach ([
                'designers' => ['المصممين', 'مسؤولو التصميم والطباعة حتى جاهزية الأوردر للتسليم', self::DESIGNER_PERMISSIONS],
                'reception' => ['الاستقبال', 'الاطلاع على العملاء وأوردراتهم والتسليمات والتحصيلات دون تعديل', self::RECEPTION_PERMISSIONS],
            ] as $name => [$alias, $description, $grants]) {
                $role = Role::query()->firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['status' => RoleStatus::ACTIVE],
                );
                $role->status = RoleStatus::ACTIVE;
                $role->save();
                $role->translations()->updateOrCreate(
                    ['locale' => 'ar'], ['alias' => $alias, 'description' => $description],
                );
                $role->syncPermissions(collect($grants)->map(fn (string $key) => $permissions[$key])->all());
                $roles[$name] = $role;
            }

            foreach ([
                'user1' => ['مصمم 1', 'designers'],
                'user2' => ['مصمم 2', 'designers'],
                'accountant1' => ['موظف استقبال', 'reception'],
            ] as $username => [$name, $role]) {
                $user = User::withTrashed()->where('username', $username)->first();
                if ($user === null) {
                    $user = User::create([
                        'username' => $username, 'name' => $name,
                        'password' => $password, 'status' => UserStatus::ACTIVE,
                    ]);
                } elseif ($user->trashed()) {
                    throw new RuntimeException("Username {$username} is soft-deleted; resolve manually rather than reassigning it.");
                } elseif ($user->getRoleNames()->all() !== [$role]) {
                    throw new RuntimeException("Username {$username} already exists with a different role; resolve this conflict manually.");
                }
                $user->syncRoles([$roles[$role]]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
