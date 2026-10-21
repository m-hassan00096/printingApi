<?php

namespace Modules\Identity\Tests\Feature\Roles;

use Modules\Identity\Tests\Feature\Roles\Support\RoleManagementTestCase;

require_once __DIR__.'/Support/RoleManagementTestCase.php';

class RoleDeleteTest extends RoleManagementTestCase
{
    public function test_can_delete_unassigned_role(): void
    {
        $role = $this->makeRole('unused', 'غير مستخدم');
        $this->deleteJson($this->roleUrl().'/'.$role->id)->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $role = $this->makeRole('employed', 'مستخدم');
        $this->makeUser($role);
        $this->deleteJson($this->roleUrl().'/'.$role->id)->assertStatus(409);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_role_with_soft_deleted_user_assignment(): void
    {
        $role = $this->makeRole('archived', 'مؤرشف');
        $this->makeUser($role)->delete();
        $this->deleteJson($this->roleUrl().'/'.$role->id)->assertStatus(409);
    }

    public function test_cannot_delete_admin_role(): void
    {
        $this->deleteJson($this->roleUrl().'/'.$this->adminRole->id)->assertStatus(409);
    }

    public function test_missing_role_returns_404(): void
    {
        $this->deleteJson($this->roleUrl().'/999999')->assertNotFound();
    }
}
