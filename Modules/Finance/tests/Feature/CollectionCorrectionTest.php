<?php
namespace Modules\Finance\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Str;
use Modules\Deliveries\Models\Delivery;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Models\Collection;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Modules\Identity\Models\Permission;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Spatie\Permission\PermissionRegistrar;

class CollectionCorrectionTest extends OrdersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'finance.collections.create', 'finance.collections.view', 'finance.collections.reverse',
            'finance.collections.correct', 'finance.balances.view',
            'deliveries.create', 'deliveries.cancel', 'deliveries.view',
        ] as $name) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function correct(int $id, string $amount, string $key, array $extras = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/finance/collections/{$id}/correct", [
                'amount' => $amount,
                'reason' => 'Correction after reconciliation',
                ...$extras,
            ]);
    }

    public function test_general_collection_correction_is_atomic_idempotent_and_retains_audit(): void
    {
        $created = $this->withHeader('Idempotency-Key', 'general-before')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '500.00', 'paymentMethod' => 0,
            ])->assertCreated();
        $oldId = $created->json('data.id');
        $corrected = $this->correct($oldId, '300.00', 'general-correct')->assertOk();
        $newId = $corrected->json('data.id');

        $this->assertNotSame($oldId, $newId);
        $this->assertDatabaseHas('finance_collections', [
            'id' => $oldId, 'status' => CollectionStatus::REVERSED->value,
            'corrected_to_id' => $newId, 'amount' => '500.00',
        ]);
        $this->assertDatabaseHas('finance_collections', [
            'id' => $newId, 'corrected_from_id' => $oldId,
            'source' => CollectionSource::GENERAL->value, 'amount' => '300.00',
            'status' => CollectionStatus::POSTED->value,
        ]);
        $this->assertDatabaseCount('finance_ledger_entries', 3);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.type', 2)->assertJsonPath('data.amount', '300.00');
        $this->correct($oldId, '300.00', 'general-correct')->assertOk()->assertJsonPath('data.id', $newId);
        $this->assertDatabaseCount('finance_collections', 2);
        $this->correct($oldId, '100.00', 'different-key')->assertStatus(409);
    }

    public function test_zero_correction_is_rejected_without_financial_changes(): void
    {
        $oldId = $this->withHeader('Idempotency-Key', 'zero-original')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '75.00', 'paymentMethod' => 0,
            ])->assertCreated()->json('data.id');
        $this->correct($oldId, '0.00', 'zero-correct')->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('finance_collections', 1);
        $this->assertDatabaseCount('finance_ledger_entries', 1);
        $this->assertSame(CollectionStatus::POSTED, Collection::findOrFail($oldId)->status);
    }

    public function test_deposit_correction_preserves_order_reference_and_uses_real_order_number(): void
    {
        $order = $this->draft();
        $this->withHeader('Idempotency-Key', 'confirm-with-deposit')
            ->postJson($this->path($order).'/confirm', [
                'depositAmount' => '200.00', 'paymentMethod' => 0,
            ])->assertOk();
        $old = Collection::query()->firstOrFail();
        $this->getJson('/api/v1/finance/collections')->assertOk()
            ->assertJsonPath('data.collections.0.orderNumber', $order->fresh()->order_number);
        $this->getJson('/api/v1/finance/collections/'.$old->id)->assertOk()
            ->assertJsonPath('data.orderNumber', $order->fresh()->order_number);
        $newId = $this->correct($old->id, '150.00', 'deposit-correct')->assertOk()->json('data.id');
        $new = Collection::findOrFail($newId);
        $this->assertSame(CollectionSource::ORDER_DEPOSIT, $new->source);
        $this->assertSame($order->id, $new->order_id);
        $this->assertSame('150.00', (string) $new->amount);
        $this->assertSame('200.00', (string) $old->fresh()->amount);
        $this->getJson('/api/v1/finance/customers/'.$this->customer->id.'/balance')
            ->assertJsonPath('data.type', 1)->assertJsonPath('data.amount', '200.00');
    }

    public function test_delivery_correction_relinks_for_later_cancellation_without_double_reversal(): void
    {
        $order = $this->draft();
        $this->withHeader('Idempotency-Key', 'confirm-delivery-correction')
            ->postJson($this->path($order).'/confirm', [])->assertOk();
        $order->items()->update(['production_stage' => 4]);
        $order->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $order->save();
        $this->withHeader('Idempotency-Key', 'deliver-correction')
            ->postJson('/api/v1/deliveries/orders/'.$order->id.'/deliver', [
                'collectedAmount' => '100.00', 'paymentMethod' => 0,
            ])->assertOk();
        $delivery = Delivery::query()->firstOrFail();
        $oldId = $delivery->collection_id;
        $newId = $this->correct($oldId, '60.00', 'delivery-correct')->assertOk()->json('data.id');
        $this->assertSame($newId, $delivery->fresh()->collection_id);
        $this->assertSame(CollectionSource::DELIVERY, Collection::findOrFail($newId)->source);
        $this->getJson('/api/v1/deliveries/'.$delivery->id)->assertOk()
            ->assertJsonPath('data.delivery.receivedAmount', '60.00');
        $this->withHeader('Idempotency-Key', 'cancel-corrected-delivery')
            ->postJson('/api/v1/deliveries/'.$delivery->id.'/cancel', [
                'reason' => 'Wrong recipient', 'fundsReturnConfirmed' => true,
            ])->assertOk();
        $this->assertSame(CollectionStatus::REVERSED, Collection::findOrFail($newId)->status);
        $this->assertDatabaseCount('finance_ledger_entries', 5);
        $this->correct($oldId, '20.00', 'cannot-correct-old')->assertStatus(409);
    }

    public function test_correction_preserves_historical_bank_account_even_after_deactivation(): void
    {
        $bank = BankAccount::query()->create([
            'bank_name' => 'Historical Bank',
            'account_number' => '1122334455',
            'is_default' => false, 'status' => PaymentDestinationStatus::ACTIVE,
        ]);
        $oldId = $this->withHeader('Idempotency-Key', 'historical-bank')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '100.00',
                'paymentMethod' => 1, 'paymentDestinationId' => $bank->id,
            ])->assertCreated()->json('data.id');
        $bank->status = PaymentDestinationStatus::INACTIVE;
        $bank->save();
        $newId = $this->correct($oldId, '70.00', 'historical-bank-correct')->assertOk()
            ->json('data.id');
        $old = Collection::findOrFail($oldId);
        $new = Collection::findOrFail($newId);
        $this->assertSame($old->bank_account_id, $new->bank_account_id);
        $this->assertSame($old->destination_snapshot, $new->destination_snapshot);
        $this->assertSame($old->customer_name_snapshot, $new->customer_name_snapshot);
        $this->assertDatabaseCount('finance_ledger_entries', 3);
    }

    public function test_correction_validation_permissions_and_unchanged_amount(): void
    {
        $oldId = $this->withHeader('Idempotency-Key', 'validation-original')
            ->postJson('/api/v1/finance/collections', [
                'customerId' => $this->customer->id, 'amount' => '50.00', 'paymentMethod' => 0,
            ])->assertCreated()->json('data.id');
        $this->correct($oldId, '50.00', 'unchanged')->assertStatus(409);
        $this->correct($oldId, '-2.00', 'invalid')->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('finance_ledger_entries', 1);
        $this->adminRole->revokePermissionTo('finance.collections.correct');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->correct($oldId, '20.00', 'unauthorized')->assertForbidden();
    }
}
