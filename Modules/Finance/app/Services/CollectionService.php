<?php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Orders\Contracts\OrderFinanceReference;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\Collection;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Support\Money;

class CollectionService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaymentDetailsService $payments,
        private readonly CustomerFinanceReference $customers,
        private readonly OrderFinanceReference $orders,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function create(array $data, int $actorId, string $keyHash): Collection
    {
        return DB::transaction(function () use ($data, $actorId, $keyHash): Collection {
            $claim = $this->idempotency->claim($actorId, 'collection_create', $keyHash, $data);
            if ($claim->result_id !== null) {
                return Collection::query()->findOrFail($claim->result_id);
            }
            $collection = $this->post($data, CollectionSource::GENERAL, $actorId);
            $this->idempotency->finish($claim, 'collection', $collection->id);
            return $collection;
        }, 3);
    }

    /** Invoked by an external owner inside an existing order-confirmation transaction. */
    public function deposit(int $orderId, int $customerId, string $amount, int $method, ?int $destinationId, ?int $actorId): Collection
    {
        return $this->post([
            'customerId' => $customerId, 'amount' => $amount,
            'paymentMethod' => $method, 'paymentDestinationId' => $destinationId,
            'orderId' => $orderId,
        ], CollectionSource::ORDER_DEPOSIT, $actorId);
    }

    public function post(array $data, CollectionSource $source, ?int $actorId): Collection
    {
        $customerId = (int) $data['customerId'];
        $amount = Money::positive($data['amount']);
        $this->ledger->lockCustomer($customerId);
        if (isset($data['orderId'])) {
            $this->orders->assertCustomerOwnsOrder((int) $data['orderId'], $customerId);
        }
        $destination = $this->payments->resolve((int) $data['paymentMethod'], $data['paymentDestinationId'] ?? null);
        $display = $this->customers->display($customerId);
        $collection = Collection::query()->create([
            'receipt_number' => 'pending-'.Str::uuid(),
            'customer_id' => $customerId, 'order_id' => $data['orderId'] ?? null,
            'customer_name_snapshot' => $display['name'], 'customer_phone_snapshot' => $display['phone'],
            'source' => $source->value, 'amount' => Money::format($amount),
            'payment_method' => (int) $data['paymentMethod'],
            'bank_account_id' => $destination['bankAccountId'], 'e_wallet_id' => $destination['eWalletId'],
            'destination_snapshot' => $destination['label'],
            'collected_on' => $data['collectedOn'] ?? now('Africa/Cairo')->toDateString(),
            'note' => $data['note'] ?? null, 'status' => CollectionStatus::POSTED->value,
            'created_by' => $actorId,
        ]);
        $collection->receipt_number = 'COL-'.now('Africa/Cairo')->format('Y').'-'.str_pad((string) $collection->id, 5, '0', STR_PAD_LEFT);
        $collection->save();

        $this->ledger->post($customerId, LedgerEntryType::COLLECTION,
            -$amount, 'collection:'.$collection->id,
            orderId: $data['orderId'] ?? null, collectionId: $collection->id,
            effectiveDate: $collection->collected_on->format('Y-m-d'), note: $collection->note);
        return $collection;
    }

    public function reverse(Collection $collection, string $reason, int $actorId, string $keyHash): void
    {
        DB::transaction(function () use ($collection, $reason, $actorId, $keyHash): void {
            $claim = $this->idempotency->claim($actorId, 'collection_reverse', $keyHash, [
                'collectionId' => $collection->id, 'reason' => $reason,
            ]);
            if ($claim->result_id !== null) {
                return;
            }
            // Reload and lock customer before the collection, consistent with other postings.
            $this->ledger->lockCustomer($collection->customer_id);
            $locked = Collection::query()->whereKey($collection->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CollectionStatus::POSTED) {
                throw new FinanceException('collection_already_reversed');
            }
            $original = LedgerEntry::query()->where('collection_id', $locked->id)
                ->where('type', LedgerEntryType::COLLECTION->value)->firstOrFail();
            $this->ledger->post($locked->customer_id, LedgerEntryType::COLLECTION_REVERSAL,
                Money::positive((string) $locked->amount), 'reverse-collection:'.$locked->id,
                orderId: $locked->order_id, collectionId: $locked->id, reversesId: $original->id,
                note: $reason);
            $locked->status = CollectionStatus::REVERSED;
            $locked->reversal_reason = $reason;
            $locked->reversed_by = $actorId;
            $locked->reversed_at = now();
            $locked->save();
            $this->idempotency->finish($claim, 'reversal', $locked->id);
        }, 3);
    }
}
