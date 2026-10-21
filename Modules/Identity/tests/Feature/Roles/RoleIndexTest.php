<?php

namespace Modules\Identity\Tests\Feature\Roles;

use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Tests\Feature\Roles\Support\RoleManagementTestCase;

require_once __DIR__.'/Support/RoleManagementTestCase.php';

class RoleIndexTest extends RoleManagementTestCase
{
    public function test_index_returns_arabic_name_status_and_user_count(): void
    {
        $staff = $this->makeRole('print_staff', 'فني طباعة');
        $this->makeUser($staff);
        $this->makeUser($staff);

        $this->getJson($this->roleUrl())
            ->assertOk()
            ->assertJsonPath('data.roles.0.name', 'فني طباعة')
            ->assertJsonPath('data.roles.0.status', 1)
            ->assertJsonPath('data.roles.0.usersCount', 2)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_index_includes_inactive_roles(): void
    {
        $this->makeRole('sleeping_role', 'مؤقت', RoleStatus::INACTIVE);
        $this->getJson($this->roleUrl())
            ->assertOk()
            ->assertJsonPath('data.roles.0.status', 0);
    }

    public function test_filter_by_status(): void
    {
        $disabled = $this->makeRole('disabled', 'مغلق', RoleStatus::INACTIVE);
        $this->makeRole('enabled', 'مفتوح');

        $this->getJson($this->roleUrl(['filter' => ['status' => 0]]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.roles.0.id', $disabled->id);
    }

    public function test_search_by_arabic_name(): void
    {
        $role = $this->makeRole('printer', 'طباعة أوفست');
        $this->makeRole('other', 'استقبال');

        $this->getJson($this->roleUrl(['filter' => ['search' => 'أوفست']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.roles.0.id', $role->id);
    }

    public function test_search_by_technical_name(): void
    {
        $role = $this->makeRole('prepress', 'تحضير');
        $this->getJson($this->roleUrl(['filter' => ['search' => 'prepress']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.roles.0.id', $role->id);
    }

    public function test_sort_by_user_count(): void
    {
        $more = $this->makeRole('more', 'أكثر');
        $this->makeUser($more);
        $this->makeUser($more);
        $this->makeRole('less', 'أقل');

        $this->getJson($this->roleUrl(['sort' => '-usersCount']))
            ->assertOk()
            ->assertJsonPath('data.roles.0.id', $more->id);
    }

    public function test_pagination_and_page_size(): void
    {
        $this->makeRole('one', 'الأول');
        $this->makeRole('two', 'الثاني');

        $this->getJson($this->roleUrl(['perPage' => 1, 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'data.roles')
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.currentPage', 2);
    }

    public function test_rejects_invalid_per_page(): void
    {
        $this->getJson($this->roleUrl(['perPage' => 101]))
            ->assertUnprocessable()->assertJsonValidationErrors('perPage');
    }

    public function test_excludes_soft_deleted_users_from_count(): void
    {
        $role = $this->makeRole('postpress', 'تشطيب');
        $this->makeUser($role)->delete();
        $this->getJson($this->roleUrl())
            ->assertOk()->assertJsonPath('data.roles.0.usersCount', 0);
    }
}
