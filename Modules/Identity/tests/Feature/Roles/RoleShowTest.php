<?php

namespace Modules\Identity\Tests\Feature\Roles;

use Modules\Identity\Tests\Feature\Roles\Support\RoleManagementTestCase;

require_once __DIR__.'/Support/RoleManagementTestCase.php';

class RoleShowTest extends RoleManagementTestCase
{
    public function test_show_returns_selected_permission_ids_and_translated_name(): void
    {
        $role = $this->makeRole('cashier', 'كاشير');
        $role->syncPermissions([$this->catalog['identity.users.view']]);
        $this->makeUser($role);

        $this->getJson($this->roleUrl().'/'.$role->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'كاشير')
            ->assertJsonPath('data.usersCount', 1)
            ->assertJsonPath('data.permissionIds.0', $this->catalog['identity.users.view']->id);
    }

    public function test_missing_role_returns_not_found(): void
    {
        $this->getJson($this->roleUrl().'/999999')->assertNotFound();
    }
}
