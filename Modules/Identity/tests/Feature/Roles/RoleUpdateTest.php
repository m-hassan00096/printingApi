<?php

namespace Modules\Identity\Tests\Feature\Roles;

use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Exceptions\Roles\ProtectedRoleException;
use Modules\Identity\Services\UserRoleService;
use Modules\Identity\Tests\Feature\Roles\Support\RoleManagementTestCase;

require_once __DIR__.'/Support/RoleManagementTestCase.php';

class RoleUpdateTest extends RoleManagementTestCase
{
    public function test_can_change_arabic_name_without_changing_technical_name(): void
    {
        $role = $this->makeRole('operator', 'عامل');
        $this->patchJson($this->roleUrl().'/'.$role->id, ['nameAr' => 'مشغل ماكينات'])
            ->assertOk()->assertJsonPath('data.nameAr', 'مشغل ماكينات');
        $this->assertSame('operator', $role->fresh()->name);
    }

    public function test_can_replace_role_permission_ids(): void
    {
        $role = $this->makeRole('operator', 'عامل');
        $role->syncPermissions([$this->catalog['identity.users.view']]);

        $this->patchJson($this->roleUrl().'/'.$role->id, [
            'permissionIds' => [$this->catalog['identity.roles.view']->id],
        ])->assertOk()->assertJsonCount(1, 'data.permissionIds');

        $this->assertFalse($role->fresh()->hasPermissionTo('identity.users.view'));
        $this->assertTrue($role->fresh()->hasPermissionTo('identity.roles.view'));
    }

    public function test_can_set_empty_permissions(): void
    {
        $role = $this->makeRole('operator', 'عامل');
        $role->syncPermissions([$this->catalog['identity.users.view']]);
        $this->patchJson($this->roleUrl().'/'.$role->id, ['permissionIds' => []])
            ->assertOk()->assertJsonCount(0, 'data.permissionIds');
    }

    public function test_disabling_role_blocks_assigned_staff_on_next_api_request(): void
    {
        $role = $this->makeRole('operator', 'عامل');
        $role->syncPermissions([$this->catalog['identity.users.view']]);
        $staff = $this->makeUser($role);

        $this->patchJson($this->roleUrl().'/'.$role->id, ['status' => 0])
            ->assertOk()->assertJsonPath('data.status', 0);

        // Detects missing global `EnsureAssignedRoleIsActive` middleware.
        $this->loginAs($staff);
        $this->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_inactive_role_cannot_be_assigned_to_new_user(): void
    {
        $role = $this->makeRole('operator', 'عامل', RoleStatus::INACTIVE);
        $user = $this->makeUser();

        $this->expectException(\Modules\Identity\Exceptions\Users\InvalidUserRoleException::class);
        app(UserRoleService::class)->assign($user, $role->id);
    }

    public function test_reactivating_role_restores_access_without_resetting_permissions(): void
    {
        $role = $this->makeRole('operator', 'عامل');
        $role->syncPermissions([$this->catalog['identity.users.view']]);
        $staff = $this->makeUser($role);
        $role->update(['status' => RoleStatus::INACTIVE]);

        $this->loginAs($staff);
        $this->getJson('/api/v1/auth/me')->assertForbidden();

        $this->loginAs($this->administrator);
        $this->patchJson($this->roleUrl().'/'.$role->id, ['status' => 1])->assertOk();

        $this->loginAs($staff);
        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_admin_cannot_be_deactivated(): void
    {
        $this->patchJson($this->roleUrl().'/'.$this->adminRole->id, ['status' => 0])
            ->assertStatus(409);
        $this->assertSame(RoleStatus::ACTIVE, $this->adminRole->fresh()->status);
    }

    public function test_admin_permissions_cannot_be_changed(): void
    {
        $before = $this->adminRole->permissions->pluck('id')->sort()->values()->all();
        $this->patchJson($this->roleUrl().'/'.$this->adminRole->id, ['permissionIds' => []])
            ->assertStatus(409);
        $this->assertSame($before, $this->adminRole->fresh()->load('permissions')->permissions->pluck('id')->sort()->values()->all());
    }

    public function test_admin_arabic_label_can_be_updated(): void
    {
        $this->patchJson($this->roleUrl().'/'.$this->adminRole->id, ['nameAr' => 'مسؤول النظام'])
            ->assertOk()->assertJsonPath('data.key', 'admin');
    }

    public function test_update_does_not_remove_permissions_if_omitted(): void
    {
        $role = $this->makeRole('operator', 'عامل');
        $role->syncPermissions([$this->catalog['identity.users.view']]);
        $this->patchJson($this->roleUrl().'/'.$role->id, ['nameAr' => 'فني'])
            ->assertOk();
        $this->assertTrue($role->fresh()->hasPermissionTo('identity.users.view'));
    }

    public function test_update_checks_duplicate_arabic_name(): void
    {
        $role = $this->makeRole('operator', 'عامل');
        $this->patchJson($this->roleUrl().'/'.$role->id, ['nameAr' => 'مدير النظام'])
            ->assertUnprocessable()->assertJsonValidationErrors('nameAr');
    }
}
