<?php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Deliveries\Contracts\DeliveryFinanceGateway;
use Modules\Deliveries\Exceptions\DeliveryException;
use Modules\Finance\Enums\BalanceType;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Models\Collection;
use Modules\Finance\Support\Money;

class FinanceDeliveryAdapter implements DeliveryFinanceGateway
{
    public function __construct(private readonly LedgerService $ledger, private readonly CollectionService $collections) {}

    public function balances(array $customerIds): array
    {
        if ($customerIds === []) return [];
        $rows = DB::table('finance_ledger_entries')->whereIn('customer_id', array_unique($customerIds))
            ->groupBy('customer_id')->select('customer_id')
            ->selectRaw('COALESCE(SUM(signed_amount),0) as account_total')
            ->pluck('account_total', 'customer_id');
        $out = [];
        foreach (array_unique($customerIds) as $id) {
            $cents = Money::cents((string) ($rows[$id] ?? '0.00'));
            $type = $cents > 0 ? BalanceType::DEBIT : ($cents < 0 ? BalanceType::CREDIT : BalanceType::ZERO);
            $out[$id] = ['amount' => Money::format(abs($cents)),
                'type' => $type->value, 'typeLabel' => $type->label()];
        }
        return $out;
    }

    public function post(int $orderId, int $customerId, string $amount, int $method,
        ?int $destinationId, int $actorId, bool $confirmCredit): int
    {
        $cents = Money::positive($amount);
        $this->ledger->lockCustomer($customerId);
        $oldBalance = $this->ledger->centsForPosting($customerId);
        if ($oldBalance - $cents < 0 && ! $confirmCredit)
            throw new DeliveryException('credit_confirmation_required');
        $collection = $this->collections->post([
            'customerId' => $customerId, 'orderId' => $orderId, 'amount' => $amount,
            'paymentMethod' => $method, 'paymentDestinationId' => $destinationId,
        ], CollectionSource::DELIVERY, $actorId);
        return $collection->id;
    }

    public function reverse(int $collectionId, int $orderId, int $customerId, int $actorId,
        string $reason, bool $fundsConfirmed): void
    {
        // Finance uses customer lock before collection lock, including its generic reversal.
        $this->ledger->lockCustomer($customerId);
        $collection = Collection::query()->whereKey($collectionId)->lockForUpdate()->first();
        if ($collection === null || $collection->customer_id !== $customerId
            || $collection->order_id !== $orderId || $collection->source !== CollectionSource::DELIVERY) {
            throw new DeliveryException('invalid_collection');
        }
        if ($collection->status === CollectionStatus::REVERSED) return;
        if ($collection->status !== CollectionStatus::POSTED) throw new DeliveryException('invalid_collection');
        if (! $fundsConfirmed) throw new DeliveryException('funds_confirmation_required');
        $this->collections->reverse($collection, $reason, $actorId,
            hash('sha256', 'delivery-cancel-collection-'.$collection->id));
    }

    public function collections(array $collectionIds): array
    {
        if ($collectionIds === []) return [];
        return Collection::query()->whereIn('id', array_unique($collectionIds))->get()
            ->mapWithKeys(fn (Collection $c) => [$c->id => [
                'id' => $c->id, 'orderId' => $c->order_id, 'customerId' => $c->customer_id,
                'receiptNumber' => $c->receipt_number, 'source' => $c->source->value,
                'sourceLabel' => $c->source->label(), 'status' => $c->status->value,
                'statusLabel' => $c->status->label(), 'amount' => (string) $c->amount,
                'paymentMethod' => $c->payment_method->value,
                'paymentMethodLabel' => $c->payment_method->label(),
                'paymentDestinationId' => $c->bank_account_id ?? $c->e_wallet_id,
                'destinationLabel' => $c->destination_snapshot,
            ]])->all();
    }
}
