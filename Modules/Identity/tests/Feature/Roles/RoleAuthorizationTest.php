<?php

namespace Modules\Identity\Tests\Feature\Roles;

use Illuminate\Support\Facades\Auth;
use Modules\Identity\Tests\Feature\Roles\Support\RoleManagementTestCase;

require_once __DIR__.'/Support/RoleManagementTestCase.php';

class RoleAuthorizationTest extends RoleManagementTestCase
{
    public function test_guest_cannot_list_roles(): void
    {
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $this->getJson($this->roleUrl())->assertUnauthorized();
    }

    public function test_no_permission_cannot_read_roles(): void
    {
        $role = $this->makeRole('none', 'بلا صلاحيات');
        $user = $this->makeUser($role);
        $this->loginAs($user->fresh());
        $this->getJson($this->roleUrl())->assertForbidden();
    }

    public function test_view_only_cannot_write_roles(): void
    {
        $role = $this->makeRole('viewonly', 'مشاهد');
        $role->givePermissionTo($this->catalog['identity.roles.view']);
        $user = $this->makeUser($role);
        $this->loginAs($user);
        $this->getJson($this->roleUrl())->assertOk();
        $this->postJson($this->roleUrl(), $this->payload())->assertForbidden();
        $this->patchJson($this->roleUrl().'/'.$role->id, ['nameAr' => 'غير مسموح'])->assertForbidden();
        $this->deleteJson($this->roleUrl().'/'.$role->id)->assertForbidden();
    }

    public function test_permission_change_takes_effect_on_next_request(): void
    {
        $role = $this->makeRole('temp', 'مؤقت');
        $role->givePermissionTo($this->catalog['identity.roles.view']);
        $user = $this->makeUser($role);

        $this->loginAs($user);
        $this->getJson($this->roleUrl())->assertOk();

        $this->loginAs($this->administrator);
        $this->patchJson($this->roleUrl().'/'.$role->id, ['permissionIds' => []])->assertOk();

        $this->loginAs($user->fresh());
        $this->getJson($this->roleUrl())->assertForbidden();
    }
}
