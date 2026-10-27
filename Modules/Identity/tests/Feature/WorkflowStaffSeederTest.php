<?php
namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Customers\Database\Seeders\CustomersPermissionSeeder;
use Modules\Deliveries\Database\Seeders\DeliveriesPermissionSeeder;
use Modules\Finance\Database\Seeders\FinancePermissionSeeder;
use Modules\Identity\Database\Seeders\WorkflowStaffSeeder;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Orders\Database\Seeders\OrdersPermissionSeeder;
use Modules\PrintingServices\Database\Seeders\PrintingServicesPermissionSeeder;
use Modules\Production\Database\Seeders\ProductionPermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowStaffSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('TABAA_WORKFLOW_STAFF_PASSWORD=mans123456');
        foreach ([
            CustomersPermissionSeeder::class, OrdersPermissionSeeder::class,
            PrintingServicesPermissionSeeder::class, ProductionPermissionSeeder::class,
            DeliveriesPermissionSeeder::class, FinancePermissionSeeder::class,
        ] as $seeder) $this->seed($seeder);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json']);
    }

    protected function tearDown(): void
    {
        putenv('TABAA_WORKFLOW_STAFF_PASSWORD');
        parent::tearDown();
    }

    private function signIn(User $user): void
    {
        $this->flushSession();
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
    }

    public function test_three_accounts_receive_exact_roles_permissions_and_password_is_not_replaced(): void
    {
        $this->seed(WorkflowStaffSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('roles', 2);

        $designers = Role::where('name', 'designers')->firstOrFail();
        $reception = Role::where('name', 'reception')->firstOrFail();
        $this->assertSame('المصممين', $designers->translations()->where('locale', 'ar')->firstOrFail()->alias);
        $this->assertSame('الاستقبال', $reception->translations()->where('locale', 'ar')->firstOrFail()->alias);
        $this->assertEqualsCanonicalizing(WorkflowStaffSeeder::DESIGNER_PERMISSIONS, $designers->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing(WorkflowStaffSeeder::RECEPTION_PERMISSIONS, $reception->permissions->pluck('name')->all());

        foreach (['user1', 'user2', 'accountant1'] as $name) {
            $user = User::where('username', $name)->firstOrFail();
            $this->assertSame(UserStatus::ACTIVE, $user->status);
            $this->assertTrue(Hash::check('mans123456', $user->password));
            $this->assertSame([$name === 'accountant1' ? 'reception' : 'designers'], $user->getRoleNames()->all());
        }

        $hash = User::where('username', 'user1')->value('password');
        putenv('TABAA_WORKFLOW_STAFF_PASSWORD');
        $this->seed(WorkflowStaffSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertSame($hash, User::where('username', 'user1')->value('password'));
    }

    public function test_designers_can_finish_production_but_cannot_deliver_or_read_orders_and_finance(): void
    {
        $this->seed(WorkflowStaffSeeder::class);
        $user = User::where('username', 'user1')->firstOrFail();
        $this->signIn($user);

        $this->getJson('/api/v1/production/items?filter[column]=0')->assertOk();
        $this->getJson('/api/v1/production/summary')->assertOk();
        $this->getJson('/api/v1/selects?selects=activeCustomers,printingServices,printingLocations')->assertOk();
        $this->getJson('/api/v1/orders')->assertForbidden();
        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->getJson('/api/v1/deliveries/ready')->assertForbidden();
        $this->postJson('/api/v1/deliveries/orders/1/deliver')->assertForbidden();
        $this->getJson('/api/v1/finance/collections')->assertForbidden();
        $this->getJson('/api/v1/dashboard')->assertForbidden();
        $this->getJson('/api/v1/settings/bank-accounts')->assertForbidden();
        $this->assertFalse($user->can('orders.view'));
        $this->assertFalse($user->can('deliveries.create'));
        $this->assertTrue($user->can('production.printing.complete'));
        $this->assertTrue($user->can('production.items.undo'));
    }

    public function test_reception_can_read_customers_orders_deliveries_collections_but_not_modify_them(): void
    {
        $this->seed(WorkflowStaffSeeder::class);
        $user = User::where('username', 'accountant1')->firstOrFail();
        $this->signIn($user);
        $this->getJson('/api/v1/customers')->assertOk();
        $this->getJson('/api/v1/orders')->assertOk();
        $this->getJson('/api/v1/deliveries/ready')->assertOk();
        $this->getJson('/api/v1/deliveries')->assertOk();
        $this->getJson('/api/v1/finance/collections')->assertOk();
        $this->getJson('/api/v1/dashboard')->assertOk();

        $this->postJson('/api/v1/finance/collections', [])->assertForbidden();
        $this->postJson('/api/v1/deliveries/orders/1/deliver')->assertForbidden();
        $this->getJson('/api/v1/production/items')->assertForbidden();
        $this->assertFalse($user->can('orders.create'));
        $this->assertFalse($user->can('finance.collections.reverse'));
        $this->assertFalse($user->can('identity.users.create'));
    }
}
