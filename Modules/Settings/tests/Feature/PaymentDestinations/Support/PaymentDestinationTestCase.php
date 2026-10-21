<?php

namespace Modules\Settings\Tests\Feature\PaymentDestinations\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class PaymentDestinationTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;
    protected Role $staffRole;

    private int $staffSequence = 0;

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

        foreach (['bank_accounts', 'e_wallets'] as $type) {
            foreach (['view', 'create', 'update', 'delete'] as $operation) {
                $permission = Permission::findOrCreate("settings.{$type}.{$operation}", 'web');
                $adminRole->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->administrator = User::create([
            'name' => 'Payments Administrator',
            'username' => 'payments_administrator',
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->administrator->assignRole($adminRole);
        $this->loginAs($this->administrator);
    }

    protected function bankUrl(?int $id = null, array $params = []): string
    {
        $url = '/api/v1/settings/bank-accounts'.($id === null ? '' : '/'.$id);

        return $url.($params ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }

    protected function walletUrl(?int $id = null, array $params = []): string
    {
        $url = '/api/v1/settings/e-wallets'.($id === null ? '' : '/'.$id);

        return $url.($params ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }

    protected function bankPayload(array $overrides = []): array
    {
        return array_replace([
            'bankName' => 'National Bank',
            'accountNumber' => '001234567890',
            'isDefault' => false,
            'status' => PaymentDestinationStatus::ACTIVE->value,
        ], $overrides);
    }

    protected function walletPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Vodafone Cash',
            'phone' => '01012345678',
            'accountHolder' => 'Ahmed Ali',
            'paymentLink' => null,
            'isDefault' => false,
            'status' => PaymentDestinationStatus::ACTIVE->value,
        ], $overrides);
    }

    protected function staff(): User
    {
        $i = ++$this->staffSequence;
        $user = User::create([
            'name' => 'Payments Staff '.$i,
            'username' => 'payments_staff_'.$i,
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

    protected function logout(): void
    {
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
    }
}
