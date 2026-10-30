<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Customers\Database\Seeders\CustomersPermissionSeeder;
use Modules\Deliveries\Database\Seeders\DeliveriesPermissionSeeder;
use Modules\Finance\Database\Seeders\FinancePermissionSeeder;
use Modules\Identity\Database\Seeders\OperationalStaffSeeder;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Orders\Database\Seeders\OrdersPermissionSeeder;
use Modules\PrintingServices\Database\Seeders\PrintingServicesPermissionSeeder;
use Modules\Production\Database\Seeders\ProductionPermissionSeeder;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationalStaffSeederTest extends TestCase
{
    use RefreshDatabase;

    private const INITIAL_PASSWORD = 'test-staff-secret-123';

    protected function setUp(): void
    {
        parent::setUp();

        putenv('TABAA_STAFF_INITIAL_PASSWORD='.self::INITIAL_PASSWORD);

        foreach ([
            CustomersPermissionSeeder::class,
            OrdersPermissionSeeder::class,
            PrintingServicesPermissionSeeder::class,
            ProductionPermissionSeeder::class,
            DeliveriesPermissionSeeder::class,
            FinancePermissionSeeder::class,
        ] as $seeder) {
            $this->seed($seeder);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json']);
    }

    protected function tearDown(): void
    {
        putenv('TABAA_STAFF_INITIAL_PASSWORD');
        parent::tearDown();
    }

    public function test_it_creates_two_designers_and_one_receptionist_with_exact_permissions(): void
    {
        $this->seed(OperationalStaffSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('roles', 2);

        $designers = Role::where('name', 'designers')->firstOrFail();
        $reception = Role::where('name', 'reception')->firstOrFail();

        $this->assertSame('المصممين', $designers->translations()->where('locale', 'ar')->firstOrFail()->alias);
        $this->assertSame('الاستقبال', $reception->translations()->where('locale', 'ar')->firstOrFail()->alias);

        $this->assertEqualsCanonicalizing(
            OperationalStaffSeeder::DESIGNER_PERMISSIONS,
            $designers->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            OperationalStaffSeeder::RECEPTION_PERMISSIONS,
            $reception->permissions->pluck('name')->all(),
        );

        foreach ([
            'user1' => 'designers',
            'user2' => 'designers',
            'user3' => 'reception',
        ] as $username => $role) {
            $user = User::where('username', $username)->firstOrFail();
            $this->assertSame(UserStatus::ACTIVE, $user->status);
            $this->assertTrue(Hash::check(self::INITIAL_PASSWORD, $user->password));
            $this->assertSame([$role], $user->getRoleNames()->all());
        }
    }

    public function test_designers_can_operate_production_but_not_deliver_or_handle_finance(): void
    {
        $this->seed(OperationalStaffSeeder::class);

        $user = User::where('username', 'user1')->firstOrFail();
        $this->actingAsStaff($user);

        $this->getJson('/api/v1/production/items?filter[column]=0')->assertOk();
        $this->getJson('/api/v1/orders')->assertForbidden();
        $this->getJson('/api/v1/deliveries/ready')->assertForbidden();
        $this->postJson('/api/v1/deliveries/orders/1/deliver')->assertForbidden();
        $this->getJson('/api/v1/finance/collections')->assertForbidden();

        $this->assertTrue($user->can('production.printing.complete'));
        $this->assertFalse($user->can('deliveries.create'));
        $this->assertFalse($user->can('orders.view'));
        $this->assertFalse($user->can('customers.create'));
    }

    public function test_reception_can_manage_customers_deliveries_and_collections_without_extra_permissions(): void
    {
        $this->seed(OperationalStaffSeeder::class);

        $user = User::where('username', 'user3')->firstOrFail();
        $this->actingAsStaff($user);

        $this->getJson('/api/v1/customers')->assertOk();
        $this->getJson('/api/v1/orders')->assertOk();
        $this->getJson('/api/v1/deliveries/ready')->assertOk();
        $this->getJson('/api/v1/deliveries')->assertOk();
        $this->getJson('/api/v1/finance/collections')->assertOk();

        foreach ([
            'customers.view', 'customers.create', 'customers.update',
            'deliveries.ready.view', 'deliveries.list', 'deliveries.view', 'deliveries.create',
            'finance.collections.view', 'finance.collections.create',
            'finance.collections.reverse', 'finance.collections.correct',
            'orders.list', 'orders.view',
        ] as $granted) {
            $this->assertTrue($user->can($granted), $granted);
        }

        foreach ([
            'customers.delete', 'orders.create', 'orders.update', 'orders.confirm',
            'deliveries.cancel', 'finance.refunds.create',
            'finance.opening_balances.create', 'identity.users.create',
            'production.design.start',
        ] as $denied) {
            $this->assertFalse($user->can($denied), $denied);
        }

        $this->getJson('/api/v1/production/items')->assertForbidden();
    }

    public function test_rerunning_does_not_reset_existing_password_or_create_more_accounts(): void
    {
        $this->seed(OperationalStaffSeeder::class);

        $user = User::where('username', 'user1')->firstOrFail();
        $user->update(['password' => 'changed-password-456']);
        $newHash = $user->fresh()->password;

        // Once the users have been created, the initialization secret can be removed.
        putenv('TABAA_STAFF_INITIAL_PASSWORD');
        $this->seed(OperationalStaffSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertSame($newHash, $user->fresh()->password);
    }

    public function test_missing_initial_password_fails_without_creating_roles_or_accounts(): void
    {
        putenv('TABAA_STAFF_INITIAL_PASSWORD');

        try {
            $this->seed(OperationalStaffSeeder::class);
            $this->fail('Expected missing password to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('TABAA_STAFF_INITIAL_PASSWORD', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_an_existing_account_with_another_role_is_rejected_without_changes(): void
    {
        $admin = Role::create(['name' => 'some-other-role', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'user1',
            'name' => 'Existing user',
            'password' => 'existing-secret-456',
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($admin);

        try {
            $this->seed(OperationalStaffSeeder::class);
            $this->fail('Expected an existing account conflict to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already exists with another role', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('roles', 1);
        $this->assertSame(['some-other-role'], $user->fresh()->getRoleNames()->all());
    }

    private function actingAsStaff(User $user): void
    {
        $this->flushSession();
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
    }
}
