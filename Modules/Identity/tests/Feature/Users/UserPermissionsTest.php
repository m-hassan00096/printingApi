<?php

namespace Modules\Identity\Tests\Feature\Users;

require_once __DIR__.'/Support/UserManagementTestCase.php';

use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Identity\Tests\Feature\Users\Support\UserManagementTestCase;

class UserPermissionsTest extends UserManagementTestCase
{
    public function test_role_without_permissions_cannot_access_any_user_management_route(): void
    {
        $user = $this->createManagedUser();
        $this->loginAs($user);

        $this->getJson($this->usersUrl())->assertForbidden();
        $this->getJson($this->usersUrl().'/'.$user->id)->assertForbidden();
        $this->postJson($this->usersUrl(), [])->assertForbidden();
        $this->patchJson($this->usersUrl().'/'.$user->id, ['name' => 'Denied'])->assertForbidden();
        $this->deleteJson($this->usersUrl().'/'.$user->id)->assertForbidden();
    }

    public function test_view_permission_allows_reading_but_not_writing(): void
    {
        $this->staffRole->givePermissionTo('identity.users.view');

        $user = $this->createManagedUser();
        $this->loginAs($user);

        $this->getJson($this->usersUrl())->assertOk();
        $this->getJson($this->usersUrl().'/'.$user->id)->assertOk();
        $this->postJson($this->usersUrl(), [])->assertForbidden();
        $this->patchJson($this->usersUrl().'/'.$user->id, ['name' => 'Denied'])->assertForbidden();
        $this->deleteJson($this->usersUrl().'/'.$user->id)->assertForbidden();
    }

    public function test_create_permission_does_not_implicitly_grant_other_permissions(): void
    {
        $this->staffRole->givePermissionTo('identity.users.create');
        $user = $this->createManagedUser();
        $this->loginAs($user);

        $this->getJson($this->usersUrl())->assertForbidden();
        $this->postJson($this->usersUrl(), [
            'name' => 'Created By Operator',
            'username' => 'operator_created',
            'password' => 'new-password-123',
            'passwordConfirmation' => 'new-password-123',
            'roleId' => $this->staffRole->id,
            'status' => 1,
        ])->assertCreated();
        $this->deleteJson($this->usersUrl().'/'.$user->id)->assertForbidden();

        $this->assertDatabaseHas('users', ['username' => 'operator_created']);
    }

    public function test_update_permission_does_not_grant_view_or_delete_permission(): void
    {
        $this->staffRole->givePermissionTo('identity.users.update');
        $user = $this->createManagedUser();
        $target = $this->createManagedUser();
        $this->loginAs($user);

        $this->patchJson($this->usersUrl().'/'.$target->id, [
            'name' => 'Updated By Staff',
        ])->assertOk();

        $this->getJson($this->usersUrl())->assertForbidden();
        $this->deleteJson($this->usersUrl().'/'.$target->id)->assertForbidden();

        $this->assertSame('Updated By Staff', $target->fresh()->name);
    }

    public function test_permissions_are_resolved_from_role_and_not_direct_user_assignment(): void
    {
        $user = $this->createManagedUser();
        $this->assertCount(0, $user->getDirectPermissions());

        $this->staffRole->givePermissionTo('identity.users.view');
        $this->loginAs($user);

        $this->getJson($this->usersUrl())->assertOk();
        $this->assertCount(0, $user->getDirectPermissions());
    }
}
