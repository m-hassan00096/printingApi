<?php

namespace Modules\Settings\Tests\Feature\ParameterValues\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

abstract class ParameterValueTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;
    protected Role $staffRole;
    protected Parameter $printingLocations;
    protected Parameter $customerTypes;

    private int $staffCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Accept' => 'application/json',
        ]);
        app()->setLocale('ar');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'status' => RoleStatus::ACTIVE,
        ]);
        $this->staffRole = Role::create([
            'name' => 'staff',
            'guard_name' => 'web',
            'status' => RoleStatus::ACTIVE,
        ]);

        // Read uses the plural key; write operations use singular keys.
        $adminRole->givePermissionTo(Permission::findOrCreate(
            'settings.view_parameter_values',
            'web',
        ));

        foreach (['create', 'update'] as $operation) {
            $permission = Permission::findOrCreate(
                'settings.'.$operation.'_parameter_value',
                'web',
            );
            $adminRole->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->administrator = User::create([
            'name' => 'Settings Administrator',
            'username' => 'settings_admin',
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->administrator->assignRole($adminRole);

        $this->printingLocations = Parameter::create(['name' => 'printing_locations']);
        $this->customerTypes = Parameter::create(['name' => 'customer_types']);

        $this->loginAs($this->administrator);
    }

    protected function url(?ParameterValue $value = null): string
    {
        $base = '/api/v1/settings/parameter-values';

        return $value === null ? $base : $base.'/'.$value->getKey();
    }

    protected function payload(array $overrides = []): array
    {
        return array_replace([
            'parameterId' => $this->printingLocations->id,
            'name' => 'Print House A',
            'status' => ParameterValueStatus::ACTIVE->value,
        ], $overrides);
    }

    protected function createValue(
        Parameter $parameter,
        string $name = 'Existing Value',
        array $overrides = [],
    ): ParameterValue {
        return $parameter->values()->create(array_replace([
            'name' => $name,
            'description' => null,
            'status' => ParameterValueStatus::ACTIVE,
        ], $overrides));
    }

    protected function createStaff(): User
    {
        $id = ++$this->staffCounter;

        $user = User::create([
            'name' => 'Settings Staff '.$id,
            'username' => 'settings_staff_'.$id,
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($this->staffRole);

        return $user;
    }

    protected function loginAs(User $user): void
    {
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
    }

    protected function logoutForTest(): void
    {
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
    }
}
