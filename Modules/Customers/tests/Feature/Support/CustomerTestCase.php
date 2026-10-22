<?php
namespace Modules\Customers\Tests\Feature\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;
use Modules\Customers\Support\CustomerPhone;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;
use Modules\Settings\Models\ParameterValue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class CustomerTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Role $adminRole;
    protected Role $staffRole;
    protected ParameterValue $wholesale;
    protected ParameterValue $retail;
    protected ParameterValue $printingLocation;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminRole = Role::create(['name' => 'customers_admin', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);
        $this->staffRole = Role::create(['name' => 'customers_staff', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);

        foreach ([
            'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.selects.view',
            'settings.delete_parameter_value', 'settings.update_parameter_value',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::create([
            'name' => 'Customer Admin',
            'username' => 'customer_admin',
            'password' => 'password123',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole($this->adminRole);
        $this->login($this->admin);

        $types = Parameter::query()->firstOrCreate(['name' => 'customer_types']);
        $printing = Parameter::query()->firstOrCreate(['name' => 'printing_locations']);
        $this->wholesale = $types->values()->create([
            'name' => 'Wholesale',
            'status' => ParameterValueStatus::ACTIVE,
        ]);
        $this->retail = $types->values()->create([
            'name' => 'Retail',
            'status' => ParameterValueStatus::ACTIVE,
        ]);
        $this->printingLocation = $printing->values()->create([
            'name' => 'Main Printer',
            'status' => ParameterValueStatus::ACTIVE,
        ]);
    }

    protected function login(User $user): void
    {
        $this->flushSession();
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
    }

    protected function customer(array $changes = []): Customer
    {
        $data = array_replace([
            'name' => 'Ahmed',
            'phone' => '01012345678',
            'address' => null,
            'customer_type_id' => $this->wholesale->id,
            'status' => CustomerStatus::ACTIVE,
        ], $changes);

        $customer = new Customer();
        $customer->fill($data);
        $customer->live_phone_hash = CustomerPhone::hash($data['phone']);
        $customer->save();

        return $customer;
    }

    protected function payload(array $changes = []): array
    {
        return array_replace([
            'name' => 'Ahmed',
            'phone' => '01012345678',
            'address' => null,
            'customerTypeId' => $this->wholesale->id,
            'status' => 1,
        ], $changes);
    }

    protected function url(Customer $customer): string
    {
        return '/api/v1/customers/'.$customer->id;
    }
}
