<?php

namespace Modules\Settings\Tests\Feature\PaymentDestinations;

require_once __DIR__.'/Support/PaymentDestinationTestCase.php';

use Modules\Settings\Enums\DefaultStatus;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Tests\Feature\PaymentDestinations\Support\PaymentDestinationTestCase;

class BankAccountTest extends PaymentDestinationTestCase
{
    private function createAccount(array $overrides = []): BankAccount
    {
        $id = $this->postJson($this->bankUrl(), $this->bankPayload($overrides))
            ->assertCreated()
            ->json('data.id');

        return BankAccount::findOrFail($id);
    }

    public function test_first_active_account_becomes_default_even_when_zero(): void
    {
        $account = $this->createAccount();

        $this->assertSame(DefaultStatus::DEFAULT, $account->is_default);
        $this->assertSame('001234567890', $account->account_number);
        $this->assertSame(PaymentDestinationStatus::ACTIVE, $account->status);
        $this->assertSame($this->administrator->id, $account->created_by);
    }

    public function test_first_inactive_account_does_not_become_default(): void
    {
        $inactive = $this->createAccount(['status' => 0, 'isDefault' => 1]);
        $this->assertSame(DefaultStatus::NOT_DEFAULT, $inactive->is_default);

        $active = $this->createAccount(['accountNumber' => '002', 'isDefault' => 0]);
        $this->assertSame(DefaultStatus::DEFAULT, $active->is_default);
    }

    public function test_another_active_account_is_not_default_without_request(): void
    {
        $default = $this->createAccount();
        $other = $this->createAccount(['accountNumber' => '002']);

        $this->assertSame(DefaultStatus::DEFAULT, $default->fresh()->is_default);
        $this->assertSame(DefaultStatus::NOT_DEFAULT, $other->is_default);
    }

    public function test_can_switch_default_atomically_within_bank_group(): void
    {
        $old = $this->createAccount();
        $new = $this->createAccount(['accountNumber' => '002']);

        $this->patchJson($this->bankUrl($new->id), ['isDefault' => 1])
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);

        $this->assertSame(DefaultStatus::NOT_DEFAULT, $old->fresh()->is_default);
        $this->assertSame(DefaultStatus::DEFAULT, $new->fresh()->is_default);
        $this->assertSame(1, BankAccount::where('is_default', DefaultStatus::DEFAULT->value)->count());
    }

    public function test_deactivating_default_promotes_another_active_account(): void
    {
        $default = $this->createAccount();
        $next = $this->createAccount(['accountNumber' => '002']);
        $this->createAccount(['accountNumber' => '003']);

        $this->patchJson($this->bankUrl($default->id), ['status' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 0)
            ->assertJsonPath('data.isDefault', 0);

        $this->assertSame(DefaultStatus::DEFAULT, $next->fresh()->is_default);
        $this->assertSame(1, BankAccount::where('is_default', DefaultStatus::DEFAULT->value)->count());
    }

    public function test_no_default_when_all_accounts_are_inactive(): void
    {
        $account = $this->createAccount();

        $this->patchJson($this->bankUrl($account->id), ['status' => 0])->assertOk();

        $this->assertSame(0, BankAccount::where('is_default', DefaultStatus::DEFAULT->value)->count());
    }

    public function test_reactivating_only_active_account_makes_it_default(): void
    {
        $account = $this->createAccount(['status' => 0]);

        $this->patchJson($this->bankUrl($account->id), ['status' => 1])
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);
    }

    public function test_can_update_account_details_without_changing_the_default(): void
    {
        $account = $this->createAccount();

        $this->patchJson($this->bankUrl($account->id), [
            'bankName' => '  Updated Bank  ',
            'accountNumber' => '00000015',
        ])
            ->assertOk()
            ->assertJsonPath('data.bankName', 'Updated Bank')
            ->assertJsonPath('data.accountNumber', '00000015')
            ->assertJsonPath('data.isDefault', 1);

        $this->assertSame($this->administrator->id, $account->fresh()->updated_by);
    }

    public function test_multiple_accounts_from_same_bank_are_allowed(): void
    {
        $this->createAccount();
        $this->createAccount(['accountNumber' => '00999']);

        $this->assertSame(2, BankAccount::count());
    }

    public function test_duplicate_account_number_is_rejected(): void
    {
        $this->createAccount();

        $this->postJson($this->bankUrl(), $this->bankPayload([
            'accountNumber' => ' 001234567890 ',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accountNumber');
    }

    public function test_account_number_is_required_as_a_string(): void
    {
        $this->postJson($this->bankUrl(), $this->bankPayload([
            'accountNumber' => 12345,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accountNumber');
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $this->postJson($this->bankUrl(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bankName', 'accountNumber', 'status']);
    }

    public function test_default_flag_is_enum_in_model_and_integer_in_api(): void
    {
        $first = $this->createAccount();
        $second = $this->createAccount(['accountNumber' => '000000999']);

        $this->assertSame(DefaultStatus::DEFAULT, $first->is_default);
        $this->assertSame(DefaultStatus::NOT_DEFAULT, $second->is_default);

        $this->getJson($this->bankUrl($first->id))
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);
        $this->getJson($this->bankUrl($second->id))
            ->assertOk()
            ->assertJsonPath('data.isDefault', 0);

        $this->patchJson($this->bankUrl($second->id), ['isDefault' => 1])
            ->assertOk()
            ->assertJsonPath('data.isDefault', 1);

        $this->assertSame(DefaultStatus::NOT_DEFAULT, $first->fresh()->is_default);
        $this->assertSame(DefaultStatus::DEFAULT, $second->fresh()->is_default);
    }

    public function test_default_flag_rejects_boolean_and_out_of_range_values(): void
    {
        $account = $this->createAccount();

        foreach ([true, false, 2, -1] as $invalid) {
            $this->postJson($this->bankUrl(), $this->bankPayload([
                'accountNumber' => 'new-number',
                'isDefault' => $invalid,
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('isDefault');

            $this->patchJson($this->bankUrl($account->id), [
                'isDefault' => $invalid,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('isDefault');
        }

        $this->assertSame(DefaultStatus::DEFAULT, $account->fresh()->is_default);
    }

    public function test_invalid_status_and_default_are_rejected(): void
    {
        $this->postJson($this->bankUrl(), $this->bankPayload([
            'status' => 5,
            'isDefault' => 'invalid',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'isDefault']);
    }

    public function test_index_supports_search_status_filter_and_pagination(): void
    {
        $target = $this->createAccount(['bankName' => 'Target Bank', 'status' => 0]);
        $this->createAccount(['bankName' => 'Other Bank', 'accountNumber' => '002']);

        $this->getJson($this->bankUrl(null, [
            'filter' => ['search' => 'Target', 'status' => 0],
            'perPage' => 1,
            'page' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('data.bankAccounts.0.id', $target->id)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.perPage', 1);
    }

    public function test_can_sort_by_public_bank_name_field(): void
    {
        $this->createAccount(['bankName' => 'Zulu Bank']);
        $first = $this->createAccount(['bankName' => 'Alpha Bank', 'accountNumber' => '002']);

        $this->getJson($this->bankUrl(null, ['sort' => 'bankName']))
            ->assertOk()
            ->assertJsonPath('data.bankAccounts.0.id', $first->id);

        $this->getJson($this->bankUrl(null, ['sort' => 'bank_name']))
            ->assertStatus(400);
    }

    public function test_index_search_matches_account_number_and_keeps_leading_zeroes(): void
    {
        $target = $this->createAccount(['accountNumber' => '000123']);
        $this->createAccount(['accountNumber' => '000456']);

        $this->getJson($this->bankUrl(null, ['filter' => ['search' => '000123']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.bankAccounts.0.id', $target->id);
    }

    public function test_index_excludes_deleted_but_includes_inactive(): void
    {
        $visible = $this->createAccount(['status' => 0]);
        $deleted = $this->createAccount(['accountNumber' => '002']);
        $this->deleteJson($this->bankUrl($deleted->id))->assertOk();

        $this->getJson($this->bankUrl())
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.bankAccounts.0.id', $visible->id);
    }

    public function test_show_returns_full_bank_account_number(): void
    {
        $account = $this->createAccount();

        $this->getJson($this->bankUrl($account->id))
            ->assertOk()
            ->assertJsonPath('data.accountNumber', '001234567890');
    }

    public function test_deleted_account_returns_not_found_and_next_default_is_selected(): void
    {
        $old = $this->createAccount();
        $next = $this->createAccount(['accountNumber' => '002']);

        $this->deleteJson($this->bankUrl($old->id))
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('bank_accounts', ['id' => $old->id]);
        $this->assertSame(DefaultStatus::DEFAULT, $next->fresh()->is_default);
        $this->getJson($this->bankUrl($old->id))->assertNotFound();
    }

    public function test_deleted_account_number_is_reserved(): void
    {
        $account = $this->createAccount();
        $this->deleteJson($this->bankUrl($account->id))->assertOk();

        $this->postJson($this->bankUrl(), $this->bankPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accountNumber');
    }

    public function test_unauthenticated_user_is_denied(): void
    {
        $this->logout();
        $this->getJson($this->bankUrl())->assertUnauthorized();
        $this->postJson($this->bankUrl(), $this->bankPayload())->assertUnauthorized();
    }

    public function test_no_permissions_cannot_read_or_mutate(): void
    {
        $account = $this->createAccount();
        $this->loginAs($this->staff());

        $this->getJson($this->bankUrl())->assertForbidden();
        $this->getJson($this->bankUrl($account->id))->assertForbidden();
        $this->postJson($this->bankUrl(), $this->bankPayload())->assertForbidden();
        $this->patchJson($this->bankUrl($account->id), ['bankName' => 'Denied'])->assertForbidden();
        $this->deleteJson($this->bankUrl($account->id))->assertForbidden();
    }

    public function test_view_permission_does_not_grant_writes(): void
    {
        $account = $this->createAccount();
        $this->staffRole->givePermissionTo('settings.bank_accounts.view');
        $this->loginAs($this->staff());

        $this->getJson($this->bankUrl($account->id))->assertOk();
        $this->postJson($this->bankUrl(), $this->bankPayload())->assertForbidden();
        $this->patchJson($this->bankUrl($account->id), ['bankName' => 'Denied'])->assertForbidden();
        $this->deleteJson($this->bankUrl($account->id))->assertForbidden();
    }

    public function test_unknown_and_deleted_accounts_cannot_be_updated(): void
    {
        $this->patchJson($this->bankUrl(999999), ['bankName' => 'No'])->assertNotFound();

        $account = $this->createAccount();
        $this->deleteJson($this->bankUrl($account->id))->assertOk();
        $this->patchJson($this->bankUrl($account->id), ['bankName' => 'No'])->assertNotFound();
    }

    public function test_rejects_unknown_filters_and_oversized_pages(): void
    {
        $this->getJson($this->bankUrl(null, ['filter' => ['secret' => 'foo']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');

        $this->getJson($this->bankUrl(null, ['perPage' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('perPage');
    }
}
