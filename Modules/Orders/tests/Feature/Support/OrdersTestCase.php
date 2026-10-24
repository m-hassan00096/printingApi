<?php
namespace Modules\Orders\Tests\Feature\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;
use Modules\Customers\Support\CustomerPhone;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Orders\Models\Order;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Models\PrintingService;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;
use Modules\Settings\Models\ParameterValue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class OrdersTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Role $adminRole;
    protected Role $staffRole;
    protected Customer $customer;
    protected Customer $secondCustomer;
    protected PrintingService $service;
    protected PrintingService $secondService;
    protected ParameterValue $wholesale;
    protected ParameterValue $retail;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Accept' => 'application/json',
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);
        $this->staffRole = Role::create(['name' => 'orders_staff', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);
        foreach ([
            'orders.list', 'orders.view', 'orders.create', 'orders.update', 'orders.delete',
            'orders.items.create', 'orders.items.update', 'orders.items.delete',
            'orders.files.upload', 'orders.files.list', 'orders.files.view', 'orders.files.delete',
            'orders.activities.view', 'orders.confirm', 'orders.cancel',
            'customers.update', 'customers.delete', 'printing_services.delete',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::create([
            'name' => 'Orders Admin', 'username' => 'orders_admin',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole($this->adminRole);
        $this->login($this->admin);

        $types = Parameter::query()->firstOrCreate(['name' => 'customer_types']);
        $this->wholesale = $types->values()->create([
            'name' => 'Wholesale', 'status' => ParameterValueStatus::ACTIVE,
        ]);
        $this->retail = $types->values()->create([
            'name' => 'Retail', 'status' => ParameterValueStatus::ACTIVE,
        ]);

        $this->customer = $this->makeCustomer('Ahmed', '01012345678', $this->wholesale->id);
        $this->secondCustomer = $this->makeCustomer('Mona', '01122223333', $this->retail->id);
        $this->service = $this->makeService('Business Cards', '10.00');
        $this->secondService = $this->makeService('Flyers', '20.00');
    }

    protected function makeCustomer(string $name, string $phone, int $typeId): Customer
    {
        $customer = new Customer();
        $customer->name = $name;
        $customer->phone = $phone;
        $customer->live_phone_hash = CustomerPhone::hash($phone);
        $customer->customer_type_id = $typeId;
        $customer->status = CustomerStatus::ACTIVE;
        $customer->save();

        return $customer;
    }

    protected function makeService(string $name, string $price): PrintingService
    {
        $service = new PrintingService();
        $service->name = $name;
        $service->live_name_hash = hash('sha256', $name);
        $service->base_price = $price;
        $service->status = PrintingServiceStatus::ACTIVE;
        $service->save();

        return $service;
    }

    protected function login(User $user): void
    {
        $this->flushSession();
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
    }

    protected function data(array $changes = []): array
    {
        return array_replace([
            'customerId' => $this->customer->id,
            'source' => 0,
            'dueDate' => now('Africa/Cairo')->addDays(4)->format('Y-m-d'),
            'notes' => 'Printing note',
            'items' => [[
                'serviceId' => $this->service->id,
                'quantity' => 100,
                'specifications' => 'Glossy double-sided',
                'agreedTotal' => '350.00',
            ]],
        ], $changes);
    }

    protected function draft(array $changes = []): Order
    {
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', $this->data($changes));
        $response->assertCreated();

        return Order::query()->latest('id')->firstOrFail();
    }

    protected function path(Order $order): string
    {
        return '/api/v1/orders/'.$order->id;
    }
}
