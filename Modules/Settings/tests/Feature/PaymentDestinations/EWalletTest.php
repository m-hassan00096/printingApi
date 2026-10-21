<?php

namespace Modules\Settings\Tests\Feature\PaymentDestinations;

require_once __DIR__.'/Support/PaymentDestinationTestCase.php';

use Modules\Settings\Enums\DefaultStatus;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Models\EWallet;
use Modules\Settings\Tests\Feature\PaymentDestinations\Support\PaymentDestinationTestCase;

class EWalletTest extends PaymentDestinationTestCase
{
    private function createWallet(array $overrides = []): EWallet
    {
        $id = $this->postJson($this->walletUrl(), $this->walletPayload($overrides))
            ->assertCreated()
            ->json('data.id');

        return EWallet::findOrFail($id);
    }

    public function test_first_active_wallet_becomes_default(): void
    {
        $wallet = $this->createWallet(['isDefault' => 0]);

        $this->assertSame(DefaultStatus::DEFAULT, $wallet->is_default);
        $this->assertSame('01012345678', $wallet->phone);
        $this->assertSame($this->administrator->id, $wallet->created_by);
    }

    public function test_inactive_wallet_does_not_become_default(): void
    {
        $wallet = $this->createWallet(['status' => 0, 'isDefault' => 1]);

        $this->assertSame(DefaultStatus::NOT_DEFAULT, $wallet->is_default);
        $this->assertSame(PaymentDestinationStatus::INACTIVE, $wallet->status);
    }

    public function test_identical_wallet_names_are_allowed_with_different_phones(): void
    {
        $this->createWallet();
        $this->createWallet(['phone' => '01112345678']);

        $this->assertSame(2, EWallet::count());
    }

    public function test_duplicate_phone_is_rejected_after_trimming(): void
    {
        $this->createWallet();

        $this->postJson($this->walletUrl(), $this->walletPayload([
            'phone' => ' 01012345678 ',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_phone_must_be_eleven_digit_egyptian_mobile(): void
    {
        foreach (['12345678901', '0101234567', '01912345678', '+201012345678', '01012-345678'] as $phone) {
            $this->postJson($this->walletUrl(), $this->walletPayload([
                'phone' => $phone,
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('phone');
        }

        $this->assertDatabaseCount('e_wallets', 0);
    }

    public function test_phone_is_not_converted_to_an_integer(): void
    {
        $this->postJson($this->walletUrl(), $this->walletPayload([
            'phone' => 1012345678,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_optional_payment_link_is_plain_text(): void
    {
        $wallet = $this->createWallet([
            'paymentLink' => 'Transfer in person',
        ]);

        $this->assertSame('Transfer in person', $wallet->payment_link);
    }

    public function test_instapay_can_be_saved_as_an_ordinary_wallet_name(): void
    {
        $wallet = $this->createWallet(['name' => 'InstaPay']);

        $this->assertSame('InstaPay', $wallet->name);
    }

    public function test_default_flag_is_enum_in_model_and_integer_in_api(): void
    {
        $first = $this->createWallet();
        $second = $this->createWallet(['phone' => '01112345678']);

        $this->assertSame(DefaultStatus::DEFAULT, $first->is_default);
        $this->assertSame(DefaultStatus::NOT_DEFAULT, $second->is_default);

        $this->getJson($this->walletUrl($first->id))
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);
        $this->getJson($this->walletUrl($second->id))
            ->assertOk()
            ->assertJsonPath('data.isDefault', 0);

        $this->patchJson($this->walletUrl($second->id), ['isDefault' => 1])
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);

        $this->assertSame(DefaultStatus::NOT_DEFAULT, $first->fresh()->is_default);
        $this->assertSame(DefaultStatus::DEFAULT, $second->fresh()->is_default);
    }

    public function test_default_flag_rejects_boolean_and_out_of_range_values(): void
    {
        $wallet = $this->createWallet();

        foreach ([true, false, 2, -1] as $invalid) {
            $this->postJson($this->walletUrl(), $this->walletPayload([
                'phone' => '01112345678',
                'isDefault' => $invalid,
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('isDefault');

            $this->patchJson($this->walletUrl($wallet->id), [
                'isDefault' => $invalid,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('isDefault');
        }

        $this->assertSame(DefaultStatus::DEFAULT, $wallet->fresh()->is_default);
    }

    public function test_required_fields_and_status_are_validated(): void
    {
        $this->postJson($this->walletUrl(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'accountHolder', 'status']);

        $this->postJson($this->walletUrl(), $this->walletPayload([
            'status' => 8,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_default_switch_only_changes_wallet_defaults(): void
    {
        $bankId = $this->postJson($this->bankUrl(), $this->bankPayload())
            ->assertCreated()
            ->json('data.id');

        $first = $this->createWallet();
        $next = $this->createWallet(['phone' => '01112345678']);

        $this->patchJson($this->walletUrl($next->id), ['isDefault' => 1])
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);

        $this->assertSame(DefaultStatus::NOT_DEFAULT, $first->fresh()->is_default);
        $this->assertSame(DefaultStatus::DEFAULT, $next->fresh()->is_default);
        $this->assertSame(DefaultStatus::DEFAULT, BankAccount::findOrFail($bankId)->is_default);
        $this->assertSame(1, EWallet::where('is_default', DefaultStatus::DEFAULT->value)->count());
    }

    public function test_deselecting_oldest_active_wallet_can_keep_it_as_default(): void
    {
        $oldest = $this->createWallet();
        $other = $this->createWallet(['phone' => '01112345678']);

        // The oldest active wallet may be selected again as the fallback.
        $this->patchJson($this->walletUrl($oldest->id), [
            'isDefault' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);

        $this->assertSame(DefaultStatus::DEFAULT, $oldest->fresh()->is_default);
        $this->assertSame(DefaultStatus::NOT_DEFAULT, $other->fresh()->is_default);
        $this->assertSame(1, EWallet::where('is_default', DefaultStatus::DEFAULT->value)->count());
    }

    public function test_deactivating_default_selects_next_active_wallet(): void
    {
        $first = $this->createWallet();
        $next = $this->createWallet(['phone' => '01112345678']);

        $this->patchJson($this->walletUrl($first->id), ['status' => 0])
            ->assertOk()
            ->assertJsonPath('data.isDefault', 0);

        $this->assertSame(DefaultStatus::DEFAULT, $next->fresh()->is_default);
    }

    public function test_wallet_default_may_be_empty_when_nothing_is_active(): void
    {
        $wallet = $this->createWallet();

        $this->patchJson($this->walletUrl($wallet->id), ['status' => 0])->assertOk();

        $this->assertSame(0, EWallet::where('is_default', DefaultStatus::DEFAULT->value)->count());
    }

    public function test_update_keeps_leading_zero_and_can_clear_payment_link(): void
    {
        $wallet = $this->createWallet(['paymentLink' => 'Before']);

        $this->patchJson($this->walletUrl($wallet->id), [
            'phone' => '01512345678',
            'accountHolder' => '  Other Holder  ',
            'paymentLink' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', '01512345678')
            ->assertJsonPath('data.accountHolder', 'Other Holder')
            ->assertJsonPath('data.paymentLink', null);

        $this->assertSame($this->administrator->id, $wallet->fresh()->updated_by);
    }

    public function test_update_can_keep_its_original_phone(): void
    {
        $wallet = $this->createWallet();

        $this->patchJson($this->walletUrl($wallet->id), [
            'phone' => '01012345678',
        ])->assertOk();
    }

    public function test_update_cannot_reuse_another_wallet_phone(): void
    {
        $first = $this->createWallet();
        $second = $this->createWallet(['phone' => '01112345678']);

        $this->patchJson($this->walletUrl($second->id), [
            'phone' => $first->phone,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertSame('01112345678', $second->fresh()->phone);
    }

    public function test_index_searches_wallet_name_phone_and_holder(): void
    {
        $wallet = $this->createWallet(['name' => 'Alpha', 'accountHolder' => 'Hassan Mahmoud']);
        $this->createWallet(['name' => 'Beta', 'phone' => '01112345678', 'accountHolder' => 'Other Person']);

        foreach (['Alpha', '0101234', 'Hassan'] as $search) {
            $this->getJson($this->walletUrl(null, ['filter' => ['search' => $search]]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.eWallets.0.id', $wallet->id);
        }
    }

    public function test_index_filters_inactive_and_paginates(): void
    {
        $inactive = $this->createWallet(['status' => 0]);
        $this->createWallet(['phone' => '01112345678']);

        $this->getJson($this->walletUrl(null, [
            'filter' => ['status' => 0],
            'page' => 1,
            'perPage' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.eWallets.0.id', $inactive->id);
    }

    public function test_show_displays_full_phone_number(): void
    {
        $wallet = $this->createWallet();

        $this->getJson($this->walletUrl($wallet->id))
            ->assertOk()
            ->assertJsonPath('data.phone', '01012345678');
    }

    public function test_delete_is_soft_and_promotes_next_default(): void
    {
        $first = $this->createWallet();
        $next = $this->createWallet(['phone' => '01112345678']);

        $this->deleteJson($this->walletUrl($first->id))
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('e_wallets', ['id' => $first->id]);
        $this->assertSame(DefaultStatus::DEFAULT, $next->fresh()->is_default);
        $this->getJson($this->walletUrl($first->id))->assertNotFound();
    }

    public function test_deleted_phone_is_reserved(): void
    {
        $wallet = $this->createWallet();
        $this->deleteJson($this->walletUrl($wallet->id))->assertOk();

        $this->postJson($this->walletUrl(), $this->walletPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_guests_are_denied(): void
    {
        $this->logout();

        $this->getJson($this->walletUrl())->assertUnauthorized();
        $this->postJson($this->walletUrl(), $this->walletPayload())->assertUnauthorized();
    }

    public function test_user_without_permissions_cannot_read_or_write(): void
    {
        $wallet = $this->createWallet();
        $this->loginAs($this->staff());

        $this->getJson($this->walletUrl())->assertForbidden();
        $this->getJson($this->walletUrl($wallet->id))->assertForbidden();
        $this->postJson($this->walletUrl(), $this->walletPayload())->assertForbidden();
        $this->patchJson($this->walletUrl($wallet->id), ['name' => 'Denied'])->assertForbidden();
        $this->deleteJson($this->walletUrl($wallet->id))->assertForbidden();
    }

    public function test_view_permission_does_not_allow_wallet_mutations(): void
    {
        $wallet = $this->createWallet();
        $this->staffRole->givePermissionTo('settings.e_wallets.view');
        $this->loginAs($this->staff());

        $this->getJson($this->walletUrl($wallet->id))->assertOk();
        $this->postJson($this->walletUrl(), $this->walletPayload())->assertForbidden();
        $this->patchJson($this->walletUrl($wallet->id), ['name' => 'Denied'])->assertForbidden();
        $this->deleteJson($this->walletUrl($wallet->id))->assertForbidden();
    }

    public function test_unknown_and_deleted_wallets_cannot_be_updated(): void
    {
        $this->patchJson($this->walletUrl(999999), ['name' => 'No'])->assertNotFound();

        $wallet = $this->createWallet();
        $this->deleteJson($this->walletUrl($wallet->id))->assertOk();
        $this->patchJson($this->walletUrl($wallet->id), ['name' => 'No'])->assertNotFound();
    }

    public function test_rejects_unknown_filters_and_oversized_pages(): void
    {
        $this->getJson($this->walletUrl(null, ['filter' => ['secret' => 'foo']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');

        $this->getJson($this->walletUrl(null, ['perPage' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('perPage');
    }
}
