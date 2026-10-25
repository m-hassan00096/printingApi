<?php

namespace Modules\Identity\Tests\Feature\Users;

require_once __DIR__.'/Support/UserManagementTestCase.php';

use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Tests\Feature\Users\Support\UserManagementTestCase;

class UserLastAdminProtectionTest extends UserManagementTestCase
{
    public function test_last_active_admin_cannot_be_deleted(): void
    {
        $this->deleteJson($this->usersUrl().'/'.$this->administrator->id)
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertNotSoftDeleted('users', ['id' => $this->administrator->id]);
    }

    public function test_last_active_admin_cannot_be_deactivated(): void
    {
        $this->patchJson($this->usersUrl().'/'.$this->administrator->id, [
            'status' => UserStatus::INACTIVE->value,
        ])->assertStatus(409);

        $this->assertSame(UserStatus::ACTIVE, $this->administrator->fresh()->status);
    }

    public function test_last_active_admin_cannot_be_demoted(): void
    {
        $this->patchJson($this->usersUrl().'/'.$this->administrator->id, [
            'roleId' => $this->staffRole->id,
        ])->assertStatus(409);

        $this->assertTrue($this->administrator->fresh()->hasRole('admin'));
    }

    public function test_last_active_admin_can_edit_non_security_fields(): void
    {
        $this->patchJson($this->usersUrl().'/'.$this->administrator->id, [
            'name' => 'Updated administrator',
        ])->assertOk()->assertJsonPath('data.name', 'Updated administrator');
    }

    public function test_inactive_admin_does_not_count_as_a_backup(): void
    {
        $this->createManagedUser(['status' => UserStatus::INACTIVE], $this->adminRole);

        $this->deleteJson($this->usersUrl().'/'.$this->administrator->id)
            ->assertStatus(409);
    }

    public function test_admin_can_be_deactivated_when_another_active_admin_exists(): void
    {
        $second = $this->createManagedUser([], $this->adminRole);
        $this->patchJson($this->usersUrl().'/'.$second->id, ['status' => 0])->assertOk();
        $this->assertSame(UserStatus::INACTIVE, $second->fresh()->status);
    }

    public function test_admin_can_be_demoted_when_another_active_admin_exists(): void
    {
        $second = $this->createManagedUser([], $this->adminRole);
        $this->patchJson($this->usersUrl().'/'.$second->id, [
            'roleId' => $this->staffRole->id,
        ])->assertOk();
        $this->assertTrue($second->fresh()->hasRole('staff'));
    }

    public function test_admin_can_be_deleted_when_another_active_admin_exists(): void
    {
        $second = $this->createManagedUser([], $this->adminRole);
        $this->deleteJson($this->usersUrl().'/'.$second->id)->assertOk();
        $this->assertSoftDeleted('users', ['id' => $second->id]);
    }
}
