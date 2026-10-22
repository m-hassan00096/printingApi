<?php

namespace Modules\Settings\Tests\Feature\Selects;

require_once __DIR__.'/../PaymentDestinations/Support/PaymentDestinationTestCase.php';

use Modules\Identity\Models\Permission;
use Modules\Settings\Models\Parameter;
use Modules\Settings\Services\Selects\PaymentDestinationSelectService;
use Modules\Settings\Tests\Feature\PaymentDestinations\Support\PaymentDestinationTestCase;

class PaymentDestinationSelectEndpointTest extends PaymentDestinationTestCase
{
    private function grantSelectPermission(): void
    {
        $this->staffRole->givePermissionTo(
            Permission::findOrCreate('settings.selects.view', 'web'),
        );
        $this->loginAs($this->staff());
    }

    private function selectsUrl(string $keys, array $params = []): string
    {
        return '/api/v1/selects?'.http_build_query(
            array_replace(['selects' => $keys], $params),
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }

    public function test_endpoint_returns_numeric_default_for_banks_and_wallets(): void
    {
        $bankId = $this->postJson($this->bankUrl(), $this->bankPayload())
            ->assertCreated()->json('data.id');
        $walletId = $this->postJson($this->walletUrl(), $this->walletPayload())
            ->assertCreated()->json('data.id');
        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('bankAccounts,eWallets'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'bankAccounts')
            ->assertJsonCount(3, 'data.0.options.0')
            ->assertJsonPath('data.0.options.0.value', $bankId)
            ->assertJsonPath('data.0.options.0.label', 'National Bank - ****7890')
            ->assertJsonPath('data.0.options.0.isDefault', 1)
            ->assertJsonPath('data.1.label', 'eWallets')
            ->assertJsonCount(3, 'data.1.options.0')
            ->assertJsonPath('data.1.options.0.value', $walletId)
            ->assertJsonPath('data.1.options.0.label', 'Vodafone Cash - ****5678')
            ->assertJsonPath('data.1.options.0.isDefault', 1)
            ->assertDontSee('001234567890')
            ->assertDontSee('01012345678');
    }

    public function test_endpoint_exposes_integer_zero_for_non_default_items(): void
    {
        $this->postJson($this->bankUrl(), $this->bankPayload())->assertCreated();
        $bankId = $this->postJson($this->bankUrl(), $this->bankPayload([
            'bankName' => 'Next Bank',
            'accountNumber' => '002345678901',
        ]))->assertCreated()->json('data.id');

        $this->postJson($this->walletUrl(), $this->walletPayload())->assertCreated();
        $walletId = $this->postJson($this->walletUrl(), $this->walletPayload([
            'name' => 'InstaPay',
            'phone' => '01112345678',
        ]))->assertCreated()->json('data.id');

        $this->grantSelectPermission();

        $response = $this->getJson($this->selectsUrl('bankAccounts,eWallets'))
            ->assertOk();

        $bank = collect($response->json('data.0.options'))->firstWhere('value', $bankId);
        $wallet = collect($response->json('data.1.options'))->firstWhere('value', $walletId);

        $this->assertSame(0, $bank['isDefault']);
        $this->assertSame(0, $wallet['isDefault']);
    }

    public function test_endpoint_preserves_requested_select_order(): void
    {
        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('eWallets,bankAccounts'))
            ->assertOk()
            ->assertJsonPath('data.0.label', 'eWallets')
            ->assertJsonPath('data.1.label', 'bankAccounts');
    }

    public function test_endpoint_excludes_inactive_and_deleted_destinations(): void
    {
        $bankId = $this->postJson($this->bankUrl(), $this->bankPayload())
            ->assertCreated()->json('data.id');
        $this->postJson($this->bankUrl(), $this->bankPayload([
            'bankName' => 'Inactive Bank',
            'accountNumber' => '002',
            'status' => 0,
        ]))->assertCreated();
        $bankDeletedId = $this->postJson($this->bankUrl(), $this->bankPayload([
            'bankName' => 'Deleted Bank',
            'accountNumber' => '003',
        ]))->assertCreated()->json('data.id');
        $this->deleteJson($this->bankUrl($bankDeletedId))->assertOk();

        $walletId = $this->postJson($this->walletUrl(), $this->walletPayload())
            ->assertCreated()->json('data.id');
        $this->postJson($this->walletUrl(), $this->walletPayload([
            'name' => 'Inactive',
            'phone' => '01112345678',
            'status' => 0,
        ]))->assertCreated();
        $walletDeletedId = $this->postJson($this->walletUrl(), $this->walletPayload([
            'name' => 'Deleted',
            'phone' => '01212345678',
        ]))->assertCreated()->json('data.id');
        $this->deleteJson($this->walletUrl($walletDeletedId))->assertOk();

        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('bankAccounts,eWallets'))
            ->assertOk()
            ->assertJsonCount(1, 'data.0.options')
            ->assertJsonPath('data.0.options.0.value', $bankId)
            ->assertJsonCount(1, 'data.1.options')
            ->assertJsonPath('data.1.options.0.value', $walletId);
    }

    public function test_endpoint_reflects_default_switches_without_stale_values(): void
    {
        $oldBankId = $this->postJson($this->bankUrl(), $this->bankPayload())
            ->assertCreated()->json('data.id');
        $newBankId = $this->postJson($this->bankUrl(), $this->bankPayload([
            'bankName' => 'New Bank',
            'accountNumber' => '002345678901',
        ]))->assertCreated()->json('data.id');

        $this->patchJson($this->bankUrl($newBankId), ['isDefault' => 1])->assertOk();

        $this->grantSelectPermission();

        $options = $this->getJson($this->selectsUrl('bankAccounts'))
            ->assertOk()->json('data.0.options');

        $this->assertSame(0, collect($options)->firstWhere('value', $oldBankId)['isDefault']);
        $this->assertSame(1, collect($options)->firstWhere('value', $newBankId)['isDefault']);
    }

    public function test_explicit_active_filter_is_supported(): void
    {
        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('bankAccounts,eWallets', [
            'params' => [
                'bankAccounts' => 'active',
                'eWallets' => 'active',
            ],
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_inactive_filter_is_not_supported(): void
    {
        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('bankAccounts', [
            'params' => ['bankAccounts' => 'inactive'],
        ]))->assertUnprocessable();

        $this->getJson($this->selectsUrl('eWallets', [
            'params' => ['eWallets' => 'all'],
        ]))->assertUnprocessable();
    }

    public function test_guest_cannot_access_payment_destination_selects(): void
    {
        $this->logout();

        $this->getJson($this->selectsUrl('bankAccounts,eWallets'))
            ->assertUnauthorized();
    }

    public function test_staff_without_select_permission_is_forbidden(): void
    {
        $this->loginAs($this->staff());

        $this->getJson($this->selectsUrl('bankAccounts'))->assertForbidden();
        $this->getJson($this->selectsUrl('eWallets'))->assertForbidden();
    }

    public function test_management_view_permissions_do_not_grant_select_access(): void
    {
        $this->staffRole->givePermissionTo('settings.bank_accounts.view');
        $this->staffRole->givePermissionTo('settings.e_wallets.view');
        $this->loginAs($this->staff());

        $this->getJson($this->selectsUrl('bankAccounts,eWallets'))
            ->assertForbidden();
    }

    public function test_select_permission_does_not_grant_management_access(): void
    {
        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('bankAccounts,eWallets'))->assertOk();
        $this->getJson($this->bankUrl())->assertForbidden();
        $this->getJson($this->walletUrl())->assertForbidden();
    }

    public function test_mixed_request_cannot_leak_options_if_another_select_is_unauthorized(): void
    {
        $this->postJson($this->bankUrl(), $this->bankPayload([
            'bankName' => 'Secret Bank Label',
        ]))->assertCreated();
        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('bankAccounts,users'))
            ->assertForbidden()
            ->assertDontSee('Secret Bank Label');
    }

    public function test_bank_and_wallet_selects_work_with_existing_lookup_selects(): void
    {
        $printing = Parameter::create(['name' => 'printing_locations']);
        $printing->values()->create([
            'name' => 'Main Printing',
            'status' => 1,
        ]);
        $customer = Parameter::create(['name' => 'customer_types']);
        $customer->values()->create([
            'name' => 'Corporate',
            'status' => 1,
        ]);

        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('printingLocations,bankAccounts,customerTypes,eWallets'))
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.label', 'printingLocations')
            ->assertJsonPath('data.1.label', 'bankAccounts')
            ->assertJsonPath('data.2.label', 'customerTypes')
            ->assertJsonPath('data.3.label', 'eWallets')
            ->assertJsonPath('data.0.options.0.label', 'Main Printing')
            ->assertJsonPath('data.2.options.0.label', 'Corporate');
    }

    public function test_empty_bank_and_wallet_lists_are_returned_as_empty_arrays(): void
    {
        $this->grantSelectPermission();

        $this->getJson($this->selectsUrl('bankAccounts,eWallets'))
            ->assertOk()
            ->assertJsonPath('data.0.options', [])
            ->assertJsonPath('data.1.options', []);
    }
}
