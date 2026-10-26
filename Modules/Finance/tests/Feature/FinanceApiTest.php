<?php
namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Str;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Finance\Models\Collection;
use Modules\Finance\Models\LedgerEntry;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Models\EWallet;
use Modules\Settings\Enums\PaymentDestinationStatus;

class FinanceApiTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'finance.balances.view', 'finance.ledger.view', 'finance.opening_balances.create',
            'finance.collections.view', 'finance.collections.create', 'finance.collections.reverse',
            'finance.refunds.view', 'finance.refunds.create',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_opening_debt_and_credit_are_exclusive_and_shown_as_integer_enum(): void
    {
        $id = $this->customer->id;
        $this->postJson("/api/v1/finance/customers/$id/opening-balance", [
            'amount' => '750.25', 'type' => 2,
        ])->assertCreated()->assertJsonPath('data', null);

        $this->getJson("/api/v1/finance/customers/$id/balance")->assertOk()
            ->assertJsonPath('data.amount', '750.25')->assertJsonPath('data.type', 2)
            ->assertJsonPath('data.typeLabel', __('finance.balance_types.credit'));

        $this->postJson("/api/v1/finance/customers/$id/opening-balance", [
            'amount' => '100.00', 'type' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseCount('finance_ledger_entries', 1);
    }

    public function test_customer_balance_overview_is_paginated_aggregated_and_opening_aware(): void
    {
        $id = $this->customer->id;
        $other = $this->secondCustomer->id;
        $this->secondCustomer->update(['status' => CustomerStatus::INACTIVE]);
        $this->postJson("/api/v1/finance/customers/{$id}/opening-balance", [
            'amount' => '1200.00', 'type' => 1,
        ])->assertCreated();
        $this->withHeader('Idempotency-Key', 'overview-collect')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $id, 'amount' => '300.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->withHeader('Idempotency-Key', 'overview-inactive-credit')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $other, 'amount' => '25.50', 'paymentMethod' => 0,
            ])->assertCreated();

        $this->getJson('/api/v1/finance/customer-balances?perPage=1&sort=-balance')->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.accounts.0.customerId', $id)
            ->assertJsonPath('data.accounts.0.amount', '900.00')
            ->assertJsonPath('data.accounts.0.type', 1)
            ->assertJsonPath('data.accounts.0.openingBalanceExists', true);

        $this->getJson('/api/v1/finance/customer-balances?filter[type]=2')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.accounts.0.customerId', $other)
            ->assertJsonPath('data.accounts.0.amount', '25.50')
            ->assertJsonPath('data.accounts.0.openingBalanceExists', false);
        $this->getJson('/api/v1/finance/customer-balances?filter[search]=0112222')
            ->assertOk()->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.accounts.0.customerId', $other);
        $this->getJson('/api/v1/finance/customer-balances?filter[type]=99')
            ->assertUnprocessable()->assertJsonValidationErrors('filter.type');
        $this->getJson('/api/v1/finance/customer-balances?filter[dateFrom]=2026-10-26')
            ->assertUnprocessable()->assertJsonValidationErrors('filter');

        $this->getJson("/api/v1/finance/customers/{$id}/opening-balance-status")->assertOk()
            ->assertJsonPath('data.openingBalanceExists', true);
        $this->getJson("/api/v1/finance/customers/{$other}/opening-balance-status")->assertOk()
            ->assertJsonPath('data.openingBalanceExists', false);
        $this->getJson("/api/v1/finance/customers/{$id}/balance")->assertOk()
            ->assertJsonPath('data.openingBalanceExists', true);
    }

    public function test_ledger_running_balance_stays_unfiltered_across_pages_and_reversals(): void
    {
        $id = $this->customer->id;
        $this->postJson("/api/v1/finance/customers/{$id}/opening-balance", [
            'amount' => '1000.00', 'type' => 1,
        ])->assertCreated();
        $first = $this->withHeader('Idempotency-Key', 'run-first')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $id, 'amount' => '200.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'run-second')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $id, 'amount' => '50.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->withHeader('Idempotency-Key', 'run-reversal')
            ->postJson('/api/v1/finance/collections/'.$second->json('data.id').'/reverse', [
                'reason' => 'Mistaken collection',
            ])->assertOk();

        $this->getJson("/api/v1/finance/customers/{$id}/ledger?filter[type]=2&perPage=1&page=1")->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.entries.0.amount', '50.00')
            ->assertJsonPath('data.entries.0.runningBalance.amount', '750.00')
            ->assertJsonPath('data.entries.0.runningBalance.type', 1)
            ->assertJsonPath('data.openingBalanceExists', true);
        $this->getJson("/api/v1/finance/customers/{$id}/ledger?filter[type]=2&perPage=1&page=2")->assertOk()
            ->assertJsonPath('data.entries.0.amount', '200.00')
            ->assertJsonPath('data.entries.0.runningBalance.amount', '800.00');
        $this->getJson("/api/v1/finance/customers/{$id}/ledger?perPage=1&page=1")->assertOk()
            ->assertJsonPath('data.entries.0.type', 4)
            ->assertJsonPath('data.entries.0.runningBalance.amount', '800.00')
            ->assertJsonPath('data.balance.amount', '800.00');
    }

    public function test_account_overview_and_opening_eligibility_have_separate_permissions(): void
    {
        $this->adminRole->revokePermissionTo('finance.balances.view');
        $this->adminRole->revokePermissionTo('finance.opening_balances.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson('/api/v1/finance/customer-balances')->assertForbidden();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/opening-balance-status')
            ->assertForbidden();
    }

    public function test_cash_collection_overpayment_becomes_credit_automatically_and_is_idempotent(): void
    {
        $payload = ['customerId' => $this->customer->id, 'amount' => '4000.00', 'paymentMethod' => 0];
        $this->withHeader('Idempotency-Key', 'collect-a')
            ->postJson('/api/v1/finance/collections', $payload)
            ->assertCreated()->assertJsonStructure(['data' => ['id', 'receiptNumber']]);

        $this->postJson('/api/v1/finance/collections', $payload)->assertCreated();
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseCount('finance_ledger_entries', 1);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '4000.00')->assertJsonPath('data.type', 2);
        $this->postJson('/api/v1/finance/collections', ['customerId' => $this->customer->id,
            'amount' => '4001.00', 'paymentMethod' => 0])->assertStatus(409);
    }

    public function test_reversing_collection_restores_debt_and_cannot_be_reversed_twice(): void
    {
        $created = $this->withHeader('Idempotency-Key', 'collection-1')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '200.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $collectionId = $created->json('data.id');
        $this->withHeader('Idempotency-Key', 'reverse-1')
            ->postJson('/api/v1/finance/collections/'.$collectionId.'/reverse', ['reason' => 'Incorrect entry'])
            ->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.type', 0)->assertJsonPath('data.amount', '0.00');
        $this->assertDatabaseCount('finance_ledger_entries', 2);
        $this->withHeader('Idempotency-Key', 'reverse-2')
            ->postJson('/api/v1/finance/collections/'.$collectionId.'/reverse', ['reason' => 'Another attempt'])
            ->assertStatus(409);
    }

    public function test_inactive_customer_old_debt_partial_collection_overpayment_and_reversal_are_consistent(): void
    {
        // An inactive customer may settle old debts, but cannot place new orders.
        $this->customer->update(['status' => CustomerStatus::INACTIVE]);
        $customerId = $this->customer->id;

        $this->postJson("/api/v1/finance/customers/{$customerId}/opening-balance", [
            'type' => 1, 'amount' => '1250.00',
        ])->assertCreated();

        $firstPayload = ['customerId' => $customerId, 'amount' => '500.00', 'paymentMethod' => 0];
        $first = $this->withHeader('Idempotency-Key', 'inactive-old-debt-partial')
            ->postJson('/api/v1/finance/collections', $firstPayload)->assertCreated();
        $this->getJson("/api/v1/finance/customers/{$customerId}/balance")->assertOk()
            ->assertJsonPath('data.type', 1)->assertJsonPath('data.amount', '750.00');

        // The second general collection settles the rest and leaves a 150.00 credit.
        $overpayPayload = ['customerId' => $customerId, 'amount' => '900.00', 'paymentMethod' => 0];
        $overpay = $this->withHeader('Idempotency-Key', 'inactive-old-debt-overpay')
            ->postJson('/api/v1/finance/collections', $overpayPayload)->assertCreated();
        $receiptId = $overpay->json('data.id');

        $this->withHeader('Idempotency-Key', 'inactive-old-debt-overpay')
            ->postJson('/api/v1/finance/collections', $overpayPayload)->assertCreated()
            ->assertJsonPath('data.id', $receiptId);
        $this->assertDatabaseCount('finance_collections', 2);
        $this->getJson("/api/v1/finance/customers/{$customerId}/balance")->assertOk()
            ->assertJsonPath('data.type', 2)->assertJsonPath('data.amount', '150.00');

        $this->withHeader('Idempotency-Key', 'inactive-old-debt-reverse')
            ->postJson('/api/v1/finance/collections/'.$receiptId.'/reverse', ['reason' => 'Duplicate receipt'])
            ->assertOk();
        $this->withHeader('Idempotency-Key', 'inactive-old-debt-reverse')
            ->postJson('/api/v1/finance/collections/'.$receiptId.'/reverse', ['reason' => 'Duplicate receipt'])
            ->assertOk();
        $this->getJson("/api/v1/finance/customers/{$customerId}/balance")->assertOk()
            ->assertJsonPath('data.type', 1)->assertJsonPath('data.amount', '750.00');
        $this->getJson('/api/v1/finance/collections/summary')->assertOk()
            ->assertJsonPath('data.totalCollected', '500.00')
            ->assertJsonPath('data.collectionsCount', 1);
        $this->assertDatabaseCount('finance_ledger_entries', 4);
        $this->assertDatabaseHas('finance_collections', [
            'id' => $first->json('data.id'), 'status' => 1,
        ]);
        $this->assertDatabaseHas('finance_collections', [
            'id' => $receiptId, 'status' => 2,
        ]);
    }

    public function test_refund_is_limited_by_credit_and_requires_reason(): void
    {
        $this->withHeader('Idempotency-Key', 'col-2')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '100.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->withHeader('Idempotency-Key', 'refund-1')
            ->postJson('/api/v1/finance/refunds', [
                'customerId' => $this->customer->id, 'amount' => '101.00',
                'paymentMethod' => 0, 'reason' => 'Customer requested cash',
            ])->assertStatus(409);
        $this->postJson('/api/v1/finance/refunds', [
            'customerId' => $this->customer->id, 'amount' => '40.00',
            'paymentMethod' => 0, 'reason' => 'Customer requested cash',
        ])->assertCreated();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '60.00')->assertJsonPath('data.type', 2);
    }

    public function test_order_confirmation_and_cancellation_post_liability_without_refunding_deposit(): void
    {
        $order = $this->draft();
        $this->withHeader('Idempotency-Key', 'finance-confirm-1')
            ->postJson($this->path($order).'/confirm', [
                'depositAmount' => '400.00', 'paymentMethod' => 0,
            ])->assertOk()->assertJsonPath('data', null);
        $order->refresh();
        $this->assertSame(OrderStatus::CONFIRMED, $order->status);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseHas('finance_ledger_entries', ['customer_id' => $this->customer->id,
            'event_key' => 'order-confirm:'.$order->id, 'signed_amount' => '350.00']);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '50.00')->assertJsonPath('data.type', 2);

        $this->withHeader('Idempotency-Key', 'finance-cancel-1')
            ->postJson($this->path($order).'/cancel', ['reason' => 'Customer changed mind'])
            ->assertOk();
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.amount', '400.00')->assertJsonPath('data.type', 2);
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseCount('finance_ledger_entries', 3);
    }

    public function test_refund_preview_tracks_credit_with_idempotent_refunds_and_blocks_insufficient_balance(): void
    {
        $id = $this->customer->id;
        $this->getJson("/api/v1/finance/customers/{$id}/refundable-credit")->assertOk()
            ->assertJsonPath('data.availableCredit', '0.00')
            ->assertJsonPath('data.canRefund', false);

        $this->withHeader('Idempotency-Key', 'refund-preview-collection')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $id, 'amount' => '320.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $this->getJson("/api/v1/finance/customers/{$id}/refundable-credit")->assertOk()
            ->assertJsonPath('data.availableCredit', '320.00')
            ->assertJsonPath('data.canRefund', true);

        $payload = ['customerId' => $id, 'amount' => '100.00',
            'paymentMethod' => 0, 'reason' => 'Customer requested a partial credit refund'];
        $first = $this->withHeader('Idempotency-Key', 'refund-preview-once')
            ->postJson('/api/v1/finance/refunds', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', 'refund-preview-once')
            ->postJson('/api/v1/finance/refunds', $payload)->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('finance_refunds', 1);
        $this->assertDatabaseCount('finance_ledger_entries', 2);
        $this->getJson("/api/v1/finance/customers/{$id}/refundable-credit")->assertOk()
            ->assertJsonPath('data.availableCredit', '220.00');

        $this->withHeader('Idempotency-Key', 'refund-preview-excess')
            ->postJson('/api/v1/finance/refunds', [
                'customerId' => $id, 'amount' => '221.00',
                'paymentMethod' => 0, 'reason' => 'Exceeds available credit',
            ])->assertStatus(409);
        $this->assertDatabaseCount('finance_refunds', 1);

        $this->adminRole->revokePermissionTo('finance.balances.view');
        $this->adminRole->revokePermissionTo('finance.ledger.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson("/api/v1/finance/customers/{$id}/refundable-credit")->assertOk();
        $this->getJson("/api/v1/finance/customers/{$id}/balance")->assertForbidden();
        $this->adminRole->revokePermissionTo('finance.refunds.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson("/api/v1/finance/customers/{$id}/refundable-credit")->assertForbidden();
    }

    public function test_refund_without_credit_returns_conflict_and_does_not_post(): void
    {
        $this->withHeader('Idempotency-Key', 'refund-zero')
            ->postJson('/api/v1/finance/refunds', [
                'customerId' => $this->customer->id, 'amount' => '10.00',
                'paymentMethod' => 0, 'reason' => 'Return funds',
            ])->assertStatus(409);
        $this->assertDatabaseCount('finance_refunds', 0);
        $this->assertDatabaseCount('finance_ledger_entries', 0);
    }

    public function test_collection_requires_destination_for_transfer_and_wallet(): void
    {
        $this->withHeader('Idempotency-Key', 'missing-destination')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '10.00',
                'paymentMethod' => 1,
            ])->assertUnprocessable()->assertJsonValidationErrors('paymentDestinationId');
    }

    public function test_bank_and_wallet_use_one_destination_key_and_summary_groups_payment_methods(): void
    {
        $bank = BankAccount::query()->create([
            'bank_name' => 'National Bank', 'account_number' => '123456780',
            'is_default' => false, 'status' => PaymentDestinationStatus::ACTIVE,
        ]);
        $wallet = EWallet::query()->create([
            'name' => 'Vodafone Cash', 'phone' => '01098765432',
            'account_holder' => 'Account Owner', 'is_default' => false,
            'status' => PaymentDestinationStatus::ACTIVE,
        ]);

        $date = now('Africa/Cairo')->subDays(2)->format('Y-m-d');
        $bankResponse = $this->withHeader('Idempotency-Key', 'bank-collection')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '1200.50',
                'paymentMethod' => 1, 'paymentDestinationId' => $bank->id,
                'collectedOn' => $date,
            ])->assertCreated();

        $this->getJson('/api/v1/finance/collections/'.$bankResponse->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.paymentDestinationId', $bank->id)
            ->assertJsonPath('data.paymentMethod', 1)
            ->assertJsonPath('data.collectedOn', $date);

        $walletResponse = $this->withHeader('Idempotency-Key', 'wallet-collection')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '300.00',
                'paymentMethod' => 2, 'paymentDestinationId' => $wallet->id,
            ])->assertCreated();

        $this->getJson('/api/v1/finance/collections/'.$walletResponse->json('data.id'))
            ->assertOk()->assertJsonPath('data.paymentDestinationId', $wallet->id);

        $this->getJson('/api/v1/finance/collections/summary')->assertOk()
            ->assertJsonPath('data.totalCollected', '1500.50')
            ->assertJsonPath('data.cashTotal', '0.00')
            ->assertJsonPath('data.bankTotal', '1200.50')
            ->assertJsonPath('data.walletTotal', '300.00')
            ->assertJsonPath('data.collectionsCount', 2);

        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/ledger')
            ->assertOk()->assertJsonPath('data.entries.1.effectiveDate', $date);

        $this->withHeader('Idempotency-Key', 'cash-with-bank')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '15.00',
                'paymentMethod' => 0, 'paymentDestinationId' => $bank->id,
            ])->assertUnprocessable()->assertJsonValidationErrors('paymentDestinationId');
    }

    public function test_inactive_destination_cannot_be_used_for_collection(): void
    {
        $bank = BankAccount::query()->create([
            'bank_name' => 'Disabled Bank', 'account_number' => '76543210',
            'is_default' => false, 'status' => PaymentDestinationStatus::INACTIVE,
        ]);

        $this->withHeader('Idempotency-Key', 'inactive-bank')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '100.00',
                'paymentMethod' => 1, 'paymentDestinationId' => $bank->id,
            ])->assertUnprocessable()->assertJsonValidationErrors('paymentDestinationId');

        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertDatabaseCount('finance_ledger_entries', 0);
    }

    public function test_payment_and_customer_references_are_validated_by_requests(): void
    {
        $this->withHeader('Idempotency-Key', '')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '10.00',
                'paymentMethod' => 0,
            ])->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key');

        $this->withHeader('Idempotency-Key', 'unknown-customer')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => 999999, 'amount' => '10.00', 'paymentMethod' => 0,
            ])->assertUnprocessable()->assertJsonValidationErrors('customerId');

        $this->withHeader('Idempotency-Key', 'invalid-order-reference')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '10.00',
                'paymentMethod' => 0, 'orderId' => 999999,
            ])->assertUnprocessable()->assertJsonValidationErrors('orderId');

        $this->withHeader('Idempotency-Key', 'zero-amount')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '0.00', 'paymentMethod' => 0,
            ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('finance_collections', 0);
        $this->assertDatabaseCount('finance_ledger_entries', 0);
    }

    public function test_each_finance_index_accepts_only_its_own_filters(): void
    {
        $this->getJson('/api/v1/finance/collections?filter[type]=1')
            ->assertUnprocessable()->assertJsonValidationErrors('filter');
        $this->getJson('/api/v1/finance/collections/summary?filter[status]=2')
            ->assertUnprocessable()->assertJsonValidationErrors('filter');
        $this->getJson('/api/v1/finance/refunds?filter[source]=1')
            ->assertUnprocessable()->assertJsonValidationErrors('filter');
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/ledger?filter[paymentMethod]=1')
            ->assertUnprocessable()->assertJsonValidationErrors('filter');
        $this->getJson('/api/v1/finance/collections?filter[dateFrom]=2026-10-24&filter[dateTo]=2026-10-23')
            ->assertUnprocessable()->assertJsonValidationErrors('filter.dateTo');
    }

    public function test_order_confirmation_uses_request_for_payment_destination_validation(): void
    {
        $draft = $this->draft();
        $this->withHeader('Idempotency-Key', 'bad-deposit-destination')
            ->postJson($this->path($draft).'/confirm', [
                'depositAmount' => '100.00', 'paymentMethod' => 1, 'paymentDestinationId' => 987654,
            ])->assertUnprocessable()->assertJsonValidationErrors('paymentDestinationId');

        $this->assertSame(OrderStatus::DRAFT, $draft->fresh()->status);
        $this->assertDatabaseCount('finance_ledger_entries', 0);
        $this->assertDatabaseCount('finance_collections', 0);
    }

    public function test_posted_ledger_entry_cannot_be_modified_or_deleted(): void
    {
        $this->postJson('/api/v1/finance/customers/'.$this->customer->id.'/opening-balance', [
            'amount' => '50.00', 'type' => 1,
        ])->assertCreated();

        $entry = LedgerEntry::query()->firstOrFail();
        try {
            $entry->signed_amount = '80.00';
            $entry->save();
            $this->fail('Expected immutable movement rejection');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Expected ledger deletion rejection');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        $this->assertDatabaseHas('finance_ledger_entries', [
            'id' => $entry->id, 'signed_amount' => '50.00',
        ]);
    }

}
