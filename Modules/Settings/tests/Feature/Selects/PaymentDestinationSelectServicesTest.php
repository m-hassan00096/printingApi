<?php

namespace Modules\Settings\Tests\Feature\Selects;

require_once __DIR__.'/../PaymentDestinations/Support/PaymentDestinationTestCase.php';

use App\Exceptions\Select\InvalidSelectRequestException;
use App\Services\Select\SelectRegistry;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Models\EWallet;
use Modules\Settings\Services\Selects\PaymentDestinationSelectService;
use Modules\Settings\Tests\Feature\PaymentDestinations\Support\PaymentDestinationTestCase;

class PaymentDestinationSelectServicesTest extends PaymentDestinationTestCase
{
    public function test_bank_and_wallet_keys_are_registered_with_shared_permission(): void
    {
        $registry = app(SelectRegistry::class);

        $this->assertSame([
            PaymentDestinationSelectService::class,
            'getBankAccounts',
            'settings.selects.view',
        ], $registry->resolve('bankAccounts'));

        $this->assertSame([
            PaymentDestinationSelectService::class,
            'getEWallets',
            'settings.selects.view',
        ], $registry->resolve('eWallets'));
    }

    public function test_bank_options_contain_only_value_label_and_integer_is_default(): void
    {
        $firstId = $this->postJson($this->bankUrl(), $this->bankPayload())
            ->assertCreated()->json('data.id');
        $secondId = $this->postJson($this->bankUrl(), $this->bankPayload([
            'bankName' => 'Another Bank',
            'accountNumber' => '000055554444',
        ]))->assertCreated()->json('data.id');

        $this->assertSame([
            ['value' => $secondId, 'label' => 'Another Bank - ****4444', 'isDefault' => 0],
            ['value' => $firstId, 'label' => 'National Bank - ****7890', 'isDefault' => 1],
        ], app(PaymentDestinationSelectService::class)->getBankAccounts());
    }

    public function test_wallet_options_contain_only_value_label_and_integer_is_default(): void
    {
        $firstId = $this->postJson($this->walletUrl(), $this->walletPayload())
            ->assertCreated()->json('data.id');
        $secondId = $this->postJson($this->walletUrl(), $this->walletPayload([
            'name' => 'InstaPay',
            'phone' => '01112345678',
        ]))->assertCreated()->json('data.id');

        $this->assertSame([
            ['value' => $secondId, 'label' => 'InstaPay - ****5678', 'isDefault' => 0],
            ['value' => $firstId, 'label' => 'Vodafone Cash - ****5678', 'isDefault' => 1],
        ], app(PaymentDestinationSelectService::class)->getEWallets());
    }

    public function test_bank_select_excludes_inactive_and_soft_deleted_accounts(): void
    {
        $active = BankAccount::create([
            'bank_name' => 'Active Bank',
            'account_number' => '0000001234',
            'is_default' => true,
            'status' => 1,
        ]);
        BankAccount::create([
            'bank_name' => 'Inactive Bank',
            'account_number' => '0000005678',
            'status' => 0,
        ]);
        $deleted = BankAccount::create([
            'bank_name' => 'Deleted Bank',
            'account_number' => '0000009876',
            'status' => 1,
        ]);
        $deleted->delete();

        $this->assertSame([
            ['value' => $active->id, 'label' => 'Active Bank - ****1234', 'isDefault' => 1],
        ], app(PaymentDestinationSelectService::class)->getBankAccounts());
    }

    public function test_wallet_select_excludes_inactive_and_soft_deleted_wallets(): void
    {
        $active = EWallet::create([
            'name' => 'Active Wallet',
            'phone' => '01012345678',
            'account_holder' => 'Someone',
            'status' => 1,
            'is_default' => true,
        ]);
        EWallet::create([
            'name' => 'Inactive Wallet',
            'phone' => '01112345678',
            'account_holder' => 'Someone',
            'status' => 0,
        ]);
        $deleted = EWallet::create([
            'name' => 'Deleted Wallet',
            'phone' => '01212345678',
            'account_holder' => 'Someone',
            'status' => 1,
        ]);
        $deleted->delete();

        $this->assertSame([
            ['value' => $active->id, 'label' => 'Active Wallet - ****5678', 'isDefault' => 1],
        ], app(PaymentDestinationSelectService::class)->getEWallets());
    }

    public function test_short_bank_identifiers_are_never_revealed_in_full(): void
    {
        $bank = BankAccount::create([
            'bank_name' => 'Short Bank',
            'account_number' => '123',
            'status' => 1,
            'is_default' => true,
        ]);

        $this->assertSame([
            ['value' => $bank->id, 'label' => 'Short Bank - ****', 'isDefault' => 1],
        ], app(PaymentDestinationSelectService::class)->getBankAccounts());
    }

    public function test_no_active_destinations_returns_empty_options(): void
    {
        $this->assertSame([], app(PaymentDestinationSelectService::class)->getBankAccounts());
        $this->assertSame([], app(PaymentDestinationSelectService::class)->getEWallets());
    }

    public function test_active_filter_is_allowed_for_both_selects(): void
    {
        $bank = BankAccount::create([
            'bank_name' => 'Bank A',
            'account_number' => '1234567',
            'status' => 1,
            'is_default' => true,
        ]);
        $wallet = EWallet::create([
            'name' => 'Wallet A',
            'phone' => '01012345678',
            'account_holder' => 'A',
            'status' => 1,
            'is_default' => true,
        ]);

        $service = app(PaymentDestinationSelectService::class);
        $this->assertSame([
            ['value' => $bank->id, 'label' => 'Bank A - ****4567', 'isDefault' => 1],
        ], $service->getBankAccounts('active'));
        $this->assertSame([
            ['value' => $wallet->id, 'label' => 'Wallet A - ****5678', 'isDefault' => 1],
        ], $service->getEWallets('active'));
    }

    public function test_bank_select_rejects_unsupported_filter(): void
    {
        $this->expectException(InvalidSelectRequestException::class);

        app(PaymentDestinationSelectService::class)->getBankAccounts('all');
    }

    public function test_wallet_select_rejects_unsupported_filter(): void
    {
        $this->expectException(InvalidSelectRequestException::class);

        app(PaymentDestinationSelectService::class)->getEWallets('inactive');
    }
}
