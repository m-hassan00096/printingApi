<?php
namespace Modules\Customers\Tests\Feature;

require_once __DIR__.'/Support/CustomerTestCase.php';

use Illuminate\Support\Facades\Auth;
use Modules\Customers\Tests\Feature\Support\CustomerTestCase;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Settings\Enums\ParameterValueStatus;

class CustomerTypeOptionsTest extends CustomerTestCase
{
    private const URL = '/api/v1/customers/customer-types';

    public function test_returns_only_active_customer_types_with_real_ids(): void
    {
        $this->retail->update(['status' => ParameterValueStatus::INACTIVE]);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.customerTypes.0.value', $this->wholesale->id)
            ->assertJsonPath('data.customerTypes.0.label', $this->wholesale->name)
            ->assertJsonCount(1, 'data.customerTypes');
    }

    public function test_customer_create_or_update_permission_alone_can_read_options(): void
    {
        foreach (['customers.create', 'customers.update', 'customers.view'] as $permission) {
            $this->staffRole->syncPermissions([Permission::findOrCreate($permission, 'web')]);
            $staff = User::create([
                'name' => 'Staff '.$permission, 'username' => str_replace('.', '_', $permission),
                'password' => 'password123', 'status' => UserStatus::ACTIVE,
            ]);
            $staff->assignRole($this->staffRole);
            $this->login($staff);

            $this->getJson(self::URL)
                ->assertOk()
                ->assertJsonCount(2, 'data.customerTypes');
        }
    }

    public function test_user_without_customer_permissions_cannot_read_options(): void
    {
        $staff = User::create([
            'name' => 'Denied', 'username' => 'denied_types',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_guest_cannot_read_customer_types(): void
    {
        $this->flushSession();
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $this->getJson(self::URL)->assertUnauthorized();
    }
}
