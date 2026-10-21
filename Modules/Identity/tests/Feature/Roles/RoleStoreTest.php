<?php

namespace Modules\Identity\Tests\Feature\Roles;

use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Models\Role;
use Modules\Identity\Tests\Feature\Roles\Support\RoleManagementTestCase;

require_once __DIR__.'/Support/RoleManagementTestCase.php';

class RoleStoreTest extends RoleManagementTestCase
{
    public function test_create_role_from_arabic_name_permission_ids_and_status(): void
    {
        $this->postJson($this->roleUrl(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nameAr', 'مصمم مطبوعات')
            ->assertJsonCount(2, 'data.permissionIds');

        $role = Role::query()->where('name', '!=', 'admin')->firstOrFail();
        $this->assertNotSame('', $role->name);
        $this->assertMatchesRegularExpression('/\A[a-z0-9-]+\z/', $role->name);
        $this->assertSame('web', $role->guard_name);
        $this->assertCount(2, $role->permissions);
    }

    public function test_same_arabic_name_gets_validation_error(): void
    {
        $this->makeRole('designer', 'مصمم مطبوعات');
        $this->postJson($this->roleUrl(), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('nameAr');
    }

    public function test_duplicate_technical_names_are_avoided_for_distinct_names(): void
    {
        $first = $this->postJson($this->roleUrl(), $this->payload());
        $second = $this->postJson($this->roleUrl(), $this->payload(['nameAr' => 'مصمم ثاني']));
        $first->assertCreated();
        $second->assertCreated();
        $this->assertNotSame($first->json('data.key'), $second->json('data.key'));
    }

    public function test_create_inactive_role(): void
    {
        $this->postJson($this->roleUrl(), $this->payload(['status' => RoleStatus::INACTIVE->value]))
            ->assertCreated()->assertJsonPath('data.status', 0);
    }

    public function test_create_role_without_permissions(): void
    {
        $this->postJson($this->roleUrl(), $this->payload(['permissionIds' => []]))
            ->assertCreated()->assertJsonCount(0, 'data.permissionIds');
    }

    public function test_nonexistent_permission_is_rejected(): void
    {
        $this->postJson($this->roleUrl(), $this->payload(['permissionIds' => [999999]]))
            ->assertUnprocessable()->assertJsonValidationErrors('permissionIds.0');
    }

    public function test_other_guard_permission_is_rejected(): void
    {
        $other = \Modules\Identity\Models\Permission::findOrCreate('api.only', 'api');
        $this->postJson($this->roleUrl(), $this->payload(['permissionIds' => [$other->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('permissionIds.0');
    }

    public function test_client_cannot_override_technical_name(): void
    {
        $this->postJson($this->roleUrl(), $this->payload(['name' => 'admin']))->assertCreated();
        $role = Role::query()->where('name', '!=', 'admin')->firstOrFail();
        $this->assertNotSame('admin', $role->name);
    }

    public function test_name_and_status_are_required(): void
    {
        $this->postJson($this->roleUrl(), ['permissionIds' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['nameAr', 'status']);
    }
}
