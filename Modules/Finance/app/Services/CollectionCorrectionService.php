<?php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\Collection;
use Modules\Finance\Support\Money;

/**
 * Atomic correction: original reversal + replacement receipt + delivery relink.
 * Lock order -> delivery -> customer -> collection to match delivery cancellation's lock order.
 */
final class CollectionCorrectionService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CollectionService $collections,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function correct(Collection $original, array $data, int $actorId, string $keyHash): ?Collection
    {
        return DB::transaction(function () use ($original, $data, $actorId, $keyHash): ?Collection {
            $claim = $this->idempotency->claim($actorId, 'collection_correct', $keyHash, [
                'collectionId' => $original->id,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'confirmCreditBalance' => (bool) ($data['confirmCreditBalance'] ?? false),
            ]);
            if ($claim->result_id !== null) {
                return $claim->result_type === 'correction_zero'
                    ? null
                    : Collection::query()->findOrFail($claim->result_id);
            }

            $order = null;
            if ($original->order_id !== null) {
                $order = DB::table('orders')->where('id', $original->order_id)->lockForUpdate()->first();
                if ($order === null || (int) $order->customer_id !== (int) $original->customer_id) {
                    throw new FinanceException('correction_order_unavailable');
                }
                if ($original->source === CollectionSource::ORDER_DEPOSIT && (int) $order->status !== 1) {
                    throw new FinanceException('correction_order_unavailable');
                }
            }

            $delivery = null;
            if ($original->source === CollectionSource::DELIVERY) {
                if ($order === null) {
                    throw new FinanceException('correction_order_unavailable');
                }
                $delivery = DB::table('deliveries')->where('collection_id', $original->id)
                    ->lockForUpdate()->first();
                if ($delivery === null || (int) $delivery->order_id !== (int) $original->order_id
                    || (int) $delivery->status !== 1) {
                    throw new FinanceException('correction_delivery_unavailable');
                }
            }

            $this->ledger->lockCustomer((int) $original->customer_id);
            $locked = Collection::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CollectionStatus::POSTED || $locked->corrected_to_id !== null) {
                throw new FinanceException('collection_already_reversed');
            }

            $oldAmount = Money::positive((string) $locked->amount);
            $newAmount = Money::cents((string) $data['amount']);
            if ($newAmount === $oldAmount) {
                throw new FinanceException('correction_unchanged');
            }

            if ($locked->source === CollectionSource::DELIVERY && $newAmount > 0) {
                // Current-read balance under customer lock prevents stale-credit decisions.
                $current = $this->ledger->centsForPosting((int) $locked->customer_id);
                if ($current + $oldAmount - $newAmount < 0
                    && ! ($data['confirmCreditBalance'] ?? false)) {
                    throw new FinanceException('correction_credit_confirmation_required');
                }
            }

            // Both postings participate in the same outer transaction, never a partial correction.
            $this->collections->reverse(
                $locked, $data['reason'], $actorId,
                hash('sha256', 'collection-correction-reverse-'.$locked->id)
            );
            $replacement = null;
            if ($newAmount > 0) {
                $replacement = $this->collections->post([
                    'customerId' => (int) $locked->customer_id,
                    'orderId' => $locked->order_id,
                    'amount' => Money::format($newAmount),
                    'paymentMethod' => $locked->payment_method->value,
                    'paymentDestinationId' => $locked->bank_account_id ?? $locked->e_wallet_id,
                    'collectedOn' => $locked->collected_on->format('Y-m-d'),
                    'note' => $locked->note,
                ], $locked->source, $actorId);
                $replacement->corrected_from_id = $locked->id;
                $replacement->save();
                $locked->corrected_to_id = $replacement->id;
                $locked->save();

                if ($delivery !== null) {
                    DB::table('deliveries')->where('id', $delivery->id)
                        ->where('collection_id', $locked->id)
                        ->update(['collection_id' => $replacement->id, 'updated_at' => now()]);
                }
            }
            $this->idempotency->finish(
                $claim, $replacement === null ? 'correction_zero' : 'correction',
                $replacement?->id ?? $locked->id
            );
            return $replacement;
        }, 3);
    }
}
