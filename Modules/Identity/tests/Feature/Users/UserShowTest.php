<?php

namespace Modules\Identity\Tests\Feature\Users;

require_once __DIR__.'/Support/UserManagementTestCase.php';

use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Tests\Feature\Users\Support\UserManagementTestCase;

class UserShowTest extends UserManagementTestCase
{
    public function test_authorized_user_can_see_user_details(): void
    {
        $user = $this->createManagedUser([
            'name' => 'Mona Samir',
            'phone' => '01012345678',
            'address' => 'Cairo',
        ]);

        $this->getJson($this->usersUrl().'/'.$user->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Mona Samir')
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonPath('data.phone', '01012345678')
            ->assertJsonPath('data.address', 'Cairo')
            ->assertJsonPath('data.status', UserStatus::ACTIVE->value)
            ->assertJsonPath('data.role.name', 'staff')
            ->assertJsonPath('data.role.alias', 'موظف');
    }

    public function test_user_details_do_not_expose_credentials(): void
    {
        $user = $this->createManagedUser();

        $this->getJson($this->usersUrl().'/'.$user->id)
            ->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_unknown_user_returns_not_found(): void
    {
        $this->getJson($this->usersUrl().'/999999')
            ->assertNotFound();
    }

    public function test_soft_deleted_user_cannot_be_viewed(): void
    {
        $user = $this->createManagedUser();
        $user->delete();

        $this->getJson($this->usersUrl().'/'.$user->id)
            ->assertNotFound();
    }

    public function test_guest_cannot_view_users(): void
    {
        $user = $this->createManagedUser();
        $this->logoutForTest();

        $this->getJson($this->usersUrl().'/'.$user->id)
            ->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_view_details(): void
    {
        $user = $this->createManagedUser();
        $this->loginAs($user);

        $this->getJson($this->usersUrl().'/'.$user->id)
            ->assertForbidden();
    }
}
