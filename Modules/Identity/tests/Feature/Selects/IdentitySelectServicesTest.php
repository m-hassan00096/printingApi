<?php

namespace Modules\Identity\Tests\Feature\Selects;

use App\Exceptions\Select\InvalidSelectRequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\Selects\PermissionSelectService;
use Modules\Identity\Services\Selects\RoleSelectService;
use Modules\Identity\Services\Selects\UserSelectService;
use Tests\TestCase;

class IdentitySelectServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ar');
    }

    private function createUser(string $username, UserStatus $status = UserStatus::ACTIVE): User
    {
        return User::create([
            'name' => 'Name '.$username,
            'username' => $username,
            'password' => 'password-123',
            'status' => $status,
        ]);
    }

    private function createRole(string $name, RoleStatus $status = RoleStatus::ACTIVE, string $guard = 'web'): Role
    {
        return Role::create([
            'name' => $name,
            'guard_name' => $guard,
            'status' => $status,
        ]);
    }

    public function test_users_select_returns_value_and_label(): void
    {
        $user = $this->createUser('mona');

        $this->assertSame([
            ['value' => $user->id, 'label' => 'Name mona'],
        ], app(UserSelectService::class)->getAllUsers());
    }

    public function test_users_select_excludes_inactive_and_soft_deleted_users(): void
    {
        $valid = $this->createUser('active');
        $this->createUser('inactive', UserStatus::INACTIVE);
        $deleted = $this->createUser('deleted');
        $deleted->delete();

        $this->assertSame([
            ['value' => $valid->id, 'label' => 'Name active'],
        ], app(UserSelectService::class)->getAllUsers());
    }

    public function test_users_select_rejects_unsupported_filter(): void
    {
        $this->expectException(InvalidSelectRequestException::class);
        app(UserSelectService::class)->getAllUsers('all');
    }

    public function test_roles_select_returns_arabic_alias_for_active_web_roles(): void
    {
        $role = $this->createRole('designer');
        $role->translations()->create(['locale' => 'ar', 'alias' => 'مصمم']);
        $this->createRole('disabled', RoleStatus::INACTIVE);
        $this->createRole('other_guard', RoleStatus::ACTIVE, 'api');

        $this->assertSame([
            ['value' => $role->id, 'label' => 'مصمم'],
        ], app(RoleSelectService::class)->getAllRoles());
    }

    public function test_roles_select_falls_back_to_technical_name_without_translation(): void
    {
        $role = $this->createRole('accountant');

        $this->assertSame([
            ['value' => $role->id, 'label' => 'accountant'],
        ], app(RoleSelectService::class)->getAllRoles());
    }

    public function test_roles_select_accepts_active_parameter(): void
    {
        $role = $this->createRole('active_role');

        $this->assertSame($role->id, app(RoleSelectService::class)->getAllRoles('active')[0]['value']);
    }

    public function test_roles_select_rejects_unsupported_filter(): void
    {
        $this->expectException(InvalidSelectRequestException::class);
        app(RoleSelectService::class)->getAllRoles('inactive');
    }

    public function test_permissions_select_returns_technical_names_as_values(): void
    {
        $permission = Permission::create([
            'name' => 'identity.users.view',
            'guard_name' => 'web',
        ]);
        Permission::create(['name' => 'api.secret', 'guard_name' => 'api']);

$this->assertSame([
    ['value' => $permission->id, 'label' => $permission->name],
], app(PermissionSelectService::class)->getAllPermissions());    }

    public function test_permissions_select_accepts_all_parameter(): void
    {
        Permission::create(['name' => 'identity.roles.view', 'guard_name' => 'web']);

        $this->assertCount(1, app(PermissionSelectService::class)->getAllPermissions('all'));
    }

    public function test_permissions_select_rejects_unsupported_filter(): void
    {
        $this->expectException(InvalidSelectRequestException::class);
        app(PermissionSelectService::class)->getAllPermissions('active');
    }
}
