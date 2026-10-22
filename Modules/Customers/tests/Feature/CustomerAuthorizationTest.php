<?php
namespace Modules\Customers\Tests\Feature;

require_once __DIR__.'/Support/CustomerTestCase.php';

use Illuminate\Support\Facades\Auth;
use Modules\Customers\Tests\Feature\Support\CustomerTestCase;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;

class CustomerAuthorizationTest extends CustomerTestCase
{
    public function test_anonymous_requests_are_rejected(): void
    {
        $this->flushSession();
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();

        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->postJson('/api/v1/customers', $this->payload())->assertUnauthorized();
        $this->getJson('/api/v1/selects?selects=customers')->assertUnauthorized();
    }

    public function test_permissions_are_enforced_per_endpoint_and_selects(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'username' => 'no_customers',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);

        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->postJson('/api/v1/customers', $this->payload())->assertForbidden();
        $this->getJson('/api/v1/selects?selects=customers')->assertForbidden();
        $this->getJson('/api/v1/selects?selects=activeCustomers')->assertForbidden();
    }
}
