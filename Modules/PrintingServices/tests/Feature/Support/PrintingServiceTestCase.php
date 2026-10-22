<?php

namespace Modules\PrintingServices\Tests\Feature\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Models\PrintingService;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;
use Modules\Settings\Models\ParameterValue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class PrintingServiceTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Role $adminRole;
    protected Role $staffRole;
    protected ParameterValue $wholesale;
    protected ParameterValue $retail;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminRole = Role::create(['name' => 'printing_admin', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);
        $this->staffRole = Role::create(['name' => 'printing_staff', 'guard_name' => 'web', 'status' => RoleStatus::ACTIVE]);

        foreach ([
            'printing_services.view',
            'printing_services.create',
            'printing_services.update',
            'printing_services.delete',
            'printing_services.selects.view',
            'printing_services.price_preview.view',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::create([
            'name' => 'Printing Service Admin',
            'username' => 'printing_admin',
            'password' => 'password123',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->admin->assignRole($this->adminRole);

        $group = Parameter::query()->firstOrCreate(['name' => 'customer_types']);
        $this->wholesale = $group->values()->create([
            'name' => 'Wholesale',
            'description' => null,
            'status' => ParameterValueStatus::ACTIVE,
        ]);
        $this->retail = $group->values()->create([
            'name' => 'Retail',
            'description' => null,
            'status' => ParameterValueStatus::ACTIVE,
        ]);

        $this->login($this->admin);
    }

    protected function historicalOrderItem(PrintingService $service, bool $softDeleted = false): void
    {
        // Orders now owns the real order_items table; old ad-hoc test tables
        // must not shadow its schema after Orders migrations are installed.
        $customer = DB::table('customers')->insertGetId([
            'name' => 'Historic Customer',
            'phone' => '02012345678',
            'live_phone_hash' => hash('sha256', '02012345678'),
            'customer_type_id' => $this->wholesale->id,
            'status' => 1,
        ]);

        $order = DB::table('orders')->insertGetId([
            'customer_id' => $customer,
            'source' => 0,
            'status' => 0,
            'customer_name_snapshot' => 'Historic Customer',
            'customer_phone_snapshot' => '02012345678',
            'customer_type_id_snapshot' => $this->wholesale->id,
            'customer_type_name_snapshot' => $this->wholesale->name,
            'total_amount' => '10.00',
        ]);

        DB::table('order_items')->insert([
            'order_id' => $order,
            'printing_service_id' => $service->id,
            'service_name_snapshot' => $service->name,
            'customer_type_id_snapshot' => $this->wholesale->id,
            'customer_type_name_snapshot' => $this->wholesale->name,
            'quantity' => 1,
            'agreed_total' => '10.00',
            'production_stage' => 0,
            'deleted_at' => $softDeleted ? now() : null,
        ]);
    }

    protected function login(User $user): void
    {
        $this->flushSession();
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
    }

    protected function payload(array $changes = []): array
    {
        return array_replace([
            'name' => 'Business Cards',
            'basePrice' => '10.00',
            'status' => PrintingServiceStatus::ACTIVE->value,
        ], $changes);
    }

    protected function service(array $changes = []): PrintingService
    {
        $name = $changes['name'] ?? 'Business Cards';
        $service = new PrintingService();
        $service->name = $name;
        $service->live_name_hash = hash('sha256', $name);
        $service->base_price = array_key_exists('basePrice', $changes)
            ? $changes['basePrice']
            : '10.00';
        $service->status = $changes['status'] ?? PrintingServiceStatus::ACTIVE;
        $service->save();

        return $service;
    }

    protected function tier(PrintingService $service, array $changes = []): int
    {
        return $service->priceTiers()->create(array_replace([
            'customer_type_id' => null,
            'min_quantity' => 100,
            'max_quantity' => 500,
            'price' => '7.50',
        ], $changes))->id;
    }
}

