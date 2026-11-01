<?php

namespace Modules\Finance\Services;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Support\Money;

final class AccountSettlementService
{
    public function __construct(
        private readonly AccountSettlementScope $scope,
        private readonly LedgerService $ledger,
        private readonly CollectionService $collections,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function preview(int $customerId, string $dateTo): array
    {
        return DB::transaction(function () use ($customerId, $dateTo): array {
            $this->ledger->lockCustomer($customerId);
            return $this->scope->build($customerId, $dateTo, true);
        }, 3);
    }

    public function confirm(int $customerId, array $data, int $actorId, string $keyHash): array
    {
        return DB::transaction(function () use ($customerId, $data, $actorId, $keyHash): array {
            $this->ledger->lockCustomer($customerId);
            $claim = $this->idempotency->claim($actorId, 'account_settlement', $keyHash,
                ['customerId' => $customerId] + $data);
            if ($claim->result_id !== null) return $this->show($customerId, (int) $claim->result_id);

            $before = $this->scope->build($customerId, $data['dateTo'], true);
            if (! hash_equals($before['previewHash'], $data['previewHash'])) $this->stale();
            $amount = Money::cents($data['amount']);
            $due = Money::cents($before['dueNow']);
            if (! $before['hasWork']) {
                throw ValidationException::withMessages(['amount' => __('settlements.empty_scope')]);
            }
            if ($amount < 0 || $amount > $due) {
                throw ValidationException::withMessages(['amount' => __('settlements.excess_payment')]);
            }
            if ($due > 0 && $amount === 0) {
                throw ValidationException::withMessages(['amount' => __('settlements.payment_required')]);
            }
            // Re-reading and locking the customer's account makes a new-key
            // duplicate harmless too, not only an HTTP retry with the same key.
            $previous = DB::table('finance_account_settlements')->where('customer_id', $customerId)
                ->where('scope_to', $data['dateTo'])->where('after_hash', $before['previewHash'])
                ->orderByDesc('id')->lockForUpdate()->first();
            if ($amount === 0 && $previous !== null) {
                $this->idempotency->finish($claim, 'account_settlement', (int) $previous->id);
                return $this->show($customerId, (int) $previous->id);
            }
            if (DB::table('finance_account_settlements')->where('customer_id', $customerId)
                ->where('preview_hash', $before['previewHash'])->exists()) $this->stale();

            $balanceAfter = Money::cents($before['balanceBefore']) - $amount;
            $status = $balanceAfter > 0 ? 'partial' : ($balanceAfter < 0 ? 'credit' : 'closed');
            $today = now('Africa/Cairo')->toDateString();
            $id = DB::table('finance_account_settlements')->insertGetId([
                'customer_id' => $customerId, 'scope_to' => $data['dateTo'], 'settled_on' => $today,
                'status' => $status, 'balance_before' => $before['balanceBefore'],
                'collected_amount' => Money::format($amount), 'balance_after' => Money::format($balanceAfter),
                'account_balance_after' => Money::format(Money::cents($before['accountBalance']) - $amount),
                'preview_hash' => $before['previewHash'], 'snapshot' => json_encode($before, JSON_THROW_ON_ERROR),
                'created_by' => $actorId, 'note' => $data['note'] ?? null, 'created_at' => now(),
            ]);
            $number = 'SET-'.now('Africa/Cairo')->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $receipt = null;
            if ($amount > 0) {
                $receipt = $this->collections->post([
                    'customerId' => $customerId, 'amount' => Money::format($amount),
                    'paymentMethod' => (int) $data['paymentMethod'],
                    'paymentDestinationId' => $data['paymentDestinationId'] ?? null,
                    'collectedOn' => $today,
                    'note' => __('settlements.collection_note', ['number' => $number])
                        .(! empty($data['note']) ? ' - '.$data['note'] : ''),
                ], CollectionSource::GENERAL, $actorId);
            }
            $after = $this->scope->build($customerId, $data['dateTo'], true);
            DB::table('finance_account_settlements')->where('id', $id)->update([
                'settlement_number' => $number, 'collection_id' => $receipt?->id,
                'after_hash' => $after['previewHash'],
                'snapshot' => json_encode(['before' => $before, 'after' => $after,
                    'receiptNumber' => $receipt?->receipt_number], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            $this->idempotency->finish($claim, 'account_settlement', $id);
            return $this->show($customerId, $id);
        }, 3);
    }

    public function history(int $customerId, int $page = 1): array
    {
        app(\Modules\Customers\Contracts\CustomerFinanceReference::class)->display($customerId);
        $rows = DB::table('finance_account_settlements')->where('customer_id', $customerId)
            ->orderByDesc('id')->paginate(15, ['*'], 'page', $page);
        return ['settlements' => $rows->getCollection()->map(fn (object $row): array => $this->resource($row, false))->all(),
            'pagination' => ['currentPage' => $rows->currentPage(), 'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(), 'total' => $rows->total()]];
    }

    public function show(int $customerId, int $id): array
    {
        $row = DB::table('finance_account_settlements')->where('customer_id', $customerId)->where('id', $id)->first();
        abort_unless($row, 404);
        return $this->resource($row, true);
    }

    private function resource(object $row, bool $details): array
    {
        $snapshot = json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $manifest = $snapshot['after']['sourceManifest'];
        $review = DB::table('deliveries')->whereIn('id', $manifest['deliveryIds'])->whereNotNull('cancelled_at')->exists();
        $review = $review || DB::table('finance_ledger_entries')->where('customer_id', $row->customer_id)
            ->where('id', '>', $manifest['ledgerMaxId'])->where(function ($query) use ($manifest): void {
                $query->whereIn('reverses_entry_id', $manifest['creditEntryIds'])
                    ->orWhere(function ($q) use ($manifest): void {
                        $q->where('type', LedgerEntryType::ORDER_CANCELLATION->value)->whereIn('order_id', $manifest['orderIds']);
                    })->orWhere('type', LedgerEntryType::REFUND->value);
            })->exists();
        $status = $review ? 'needs_review' : $row->status;
        $money = static fn ($value): string => Money::format(Money::cents((string) $value));
        $result = [
            'id' => (int) $row->id, 'number' => $row->settlement_number,
            'customerId' => (int) $row->customer_id, 'dateTo' => $row->scope_to, 'settledOn' => $row->settled_on,
            'status' => $status, 'originalStatus' => $row->status, 'statusLabel' => __('settlements.status.'.$status),
            'needsReview' => $review,
            'balanceBefore' => $money($row->balance_before), 'amountCollected' => $money($row->collected_amount),
            'balanceAfter' => $money($row->balance_after), 'accountBalanceAfter' => $money($row->account_balance_after),
            'collectionId' => $row->collection_id === null ? null : (int) $row->collection_id,
            'receiptNumber' => $snapshot['receiptNumber'], 'createdAt' => $row->created_at,
            'createdBy' => (int) $row->created_by, 'note' => $row->note,
        ];
        if ($details) $result['snapshot'] = $snapshot;
        return $result;
    }

    private function stale(): never
    {
        throw new HttpResponseException(ApiResponse::error(
            message: __('settlements.stale_preview'), status: HttpStatusCode::CONFLICT,
        ));
    }
}
