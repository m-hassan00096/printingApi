<?php

namespace Modules\Settings\Tests\Feature\Seeders;

require_once __DIR__.'/../ParameterValues/Support/ParameterValueTestCase.php';

use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Settings\Database\Seeders\SettingsDatabaseSeeder;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Models\EWallet;
use Modules\Settings\Models\Parameter;
use Modules\Settings\Models\ParameterValue;
use Modules\Settings\Tests\Feature\ParameterValues\Support\ParameterValueTestCase;

class SettingsDatabaseSeederTest extends ParameterValueTestCase
{
    public function test_module_seeder_initializes_parameter_groups_and_permissions_without_payment_data(): void
    {
        $this->seed(SettingsDatabaseSeeder::class);

        $this->assertSame(2, Parameter::query()->count());
        $this->assertTrue(Parameter::where('name', 'printing_locations')->exists());
        $this->assertTrue(Parameter::where('name', 'customer_types')->exists());
        $this->assertTrue(Permission::where('name', 'settings.selects.view')->exists());
        $this->assertTrue(Permission::where('name', 'settings.bank_accounts.create')->exists());
        $this->assertTrue(Permission::where('name', 'settings.e_wallets.delete')->exists());
        $this->assertSame(0, BankAccount::query()->count());
        $this->assertSame(0, EWallet::query()->count());
    }

    public function test_module_seeder_grants_settings_permissions_to_existing_admin_role(): void
    {
        $this->assertFalse($this->administrator->can('settings.selects.view'));

        $this->seed(SettingsDatabaseSeeder::class);

        $adminRole = Role::findByName('admin', 'web');
        $this->assertTrue($adminRole->hasPermissionTo('settings.selects.view'));
        $this->assertTrue($adminRole->hasPermissionTo('settings.bank_accounts.create'));
        $this->assertTrue($adminRole->hasPermissionTo('settings.e_wallets.delete'));
        $this->assertTrue($this->administrator->fresh()->can('settings.selects.view'));
    }

    public function test_module_seeder_is_idempotent_and_preserves_existing_values(): void
    {
        $location = $this->createValue($this->printingLocations, 'Configured Location', [
            'status' => ParameterValueStatus::INACTIVE,
        ]);

        $this->seed(SettingsDatabaseSeeder::class);
        $this->seed(SettingsDatabaseSeeder::class);

        $this->assertSame(2, Parameter::query()->count());
        $this->assertSame(1, ParameterValue::withTrashed()->count());
        $this->assertSame('Configured Location', $location->fresh()->name);
        $this->assertSame(ParameterValueStatus::INACTIVE, $location->fresh()->status);
    }
}
