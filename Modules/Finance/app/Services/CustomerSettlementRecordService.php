<?php

namespace Modules\Finance\Services;

use App\Enums\HttpStatusCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Support\Money;

class CustomerSettlementRecordService
{
    public function __construct(
        private readonly CustomerFinanceReference $customers,
        private readonly CustomerSettlementService $statements,
        private readonly LedgerService $ledger,
        private readonly CollectionService $collections,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(int $customerId, int $page = 1): array
    {
        $this->customers->display($customerId);
        $query = DB::table('finance_customer_settlements as s')
            ->leftJoin('finance_collections as c', 'c.id', '=', 's.collection_id')
            ->where('s.customer_id', $customerId)
            ->select('s.*', 'c.status as collection_status');
        $lastEnd = (clone $query)->max('date_to');
        $results = (clone $query)->orderByDesc('id')->paginate(15, ['*'], 'page', $page);

        return [
            'settlements' => $results->getCollection()->map(
                fn (object $record): array => $this->summary($record)
            )->all(),
            'nextDateFrom' => $lastEnd === null ? null :
                CarbonImmutable::parse($lastEnd)->addDay()->toDateString(),
            'pagination' => [
                'currentPage' => $results->currentPage(), 'lastPage' => $results->lastPage(),
                'perPage' => $results->perPage(), 'total' => $results->total(),
            ],
        ];
    }

    public function show(int $customerId, int $id): array
    {
        $this->customers->display($customerId);
        $record = DB::table('finance_customer_settlements as s')
            ->leftJoin('finance_collections as c', 'c.id', '=', 's.collection_id')
            ->where('s.customer_id', $customerId)->where('s.id', $id)
            ->select('s.*', 'c.status as collection_status')->firstOrFail();

        return $this->summary($record) + [
            'note' => $record->note,
            'discountReason' => $record->discount_reason,
            'collectionId' => $record->collection_id === null ? null : (int) $record->collection_id,
            'statement' => json_decode((string) $record->statement_snapshot, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    public function create(int $customerId, array $data, int $actorId, string $keyHash): array
    {
        return DB::transaction(function () use ($customerId, $data, $actorId, $keyHash): array {
            // Match Finance's global lock ordering to prevent overlapping submissions.
            $this->ledger->lockCustomer($customerId);
            $claim = $this->idempotency->claim($actorId, 'settlement_create', $keyHash,
                ['customerId' => $customerId] + $data);
            if ($claim->result_id !== null) {
                return $this->show($customerId, (int) $claim->result_id);
            }

            $latest = DB::table('finance_customer_settlements')->where('customer_id', $customerId)
                ->orderByDesc('date_to')->lockForUpdate()->value('date_to');
            if ($latest !== null && $data['dateFrom'] <= $latest) {
                throw new FinanceException('settlement_period_overlap', HttpStatusCode::UNPROCESSABLE_ENTITY);
            }

            $before = $this->statements->statement($customerId, $data['dateFrom'], $data['dateTo']);
            $due = Money::cents($before['closingBalance']);
            $discount = Money::cents($data['discountAmount']);
            $collected = Money::cents($data['collectedAmount']);
            $settledOn = now('Africa/Cairo')->toDateString();
            // Both the delivered-only statement AND the real Finance balance must support
            // a discount. A confirmed but undelivered item is never discount-eligible here.
            $financeDebt = $this->ledger->centsForPosting($customerId);
            if ($discount > 0 && ($due <= 0 || $discount > $due || $discount > $financeDebt)) {
                throw new FinanceException('settlement_discount_exceeds_due', HttpStatusCode::UNPROCESSABLE_ENTITY);
            }
            if ($collected > 0 && ($due <= 0 || $collected > $due - $discount
                || $discount + $collected > $financeDebt)) {
                throw new FinanceException('settlement_collection_exceeds_due', HttpStatusCode::UNPROCESSABLE_ENTITY);
            }

            $id = DB::table('finance_customer_settlements')->insertGetId([
                'customer_id' => $customerId,
                'date_from' => $data['dateFrom'], 'date_to' => $data['dateTo'],
                'settled_on' => $settledOn,
                'balance_before' => $before['closingBalance'],
                'balance_after' => $before['closingBalance'],
                'discount_amount' => Money::format($discount),
                'discount_reason' => $discount > 0 ? trim((string) $data['discountReason']) : null,
                'collected_amount' => Money::format($collected),
                'note' => $data['note'] ?? null,
                'statement_snapshot' => '{}',
                'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);

            if ($discount > 0) {
                $this->ledger->post($customerId, LedgerEntryType::SETTLEMENT_DISCOUNT, -$discount,
                    'settlement-discount:'.$id, effectiveDate: $settledOn,
                    note: trim((string) $data['discountReason']));
            }

            $collectionId = null;
            if ($collected > 0) {
                $collection = $this->collections->post([
                    'customerId' => $customerId, 'amount' => Money::format($collected),
                    'paymentMethod' => $data['paymentMethod'],
                    'paymentDestinationId' => $data['paymentDestinationId'] ?? null,
                    'collectedOn' => $settledOn,
                    'note' => 'تحصيل تصفية #'.$id.(! empty($data['note']) ? ' — '.$data['note'] : ''),
                ], CollectionSource::GENERAL, $actorId);
                $collectionId = $collection->id;
            }

            // The work period is independent from the real settlement date. Extend
            // the frozen report with the two actual-day movements, even when the
            // selected dateTo is in the past. Never backdate a cash receipt.
            $after = $before;
            $after['settlementOn'] = $settledOn;
            $running = $due;
            if ($discount > 0) {
                $running -= $discount;
                $after['discountsTotal'] = Money::format(
                    Money::cents((string) ($after['discountsTotal'] ?? '0.00')) + $discount
                );
                $after['lines'][] = [
                    'key' => 'settlement-discount:'.$id,
                    'kind' => 'settlement_discount',
                    'date' => $settledOn,
                    'description' => 'خصم تصفية حساب — '.trim((string) $data['discountReason']),
                    'orderId' => null, 'orderNumber' => null, 'receiptNumber' => null,
                    'quantity' => null, 'unitPrice' => null,
                    'signedAmount' => Money::format(-$discount),
                    'runningBalance' => Money::format($running),
                ];
            }
            if ($collected > 0) {
                $running -= $collected;
                $after['paymentsTotal'] = Money::format(Money::cents($after['paymentsTotal']) + $collected);
                $after['lines'][] = [
                    'key' => 'settlement-collection:'.$collectionId,
                    'kind' => 'collection',
                    'date' => $settledOn,
                    'description' => 'تحصيل عند تصفية الحساب',
                    'orderId' => null, 'orderNumber' => null,
                    'receiptNumber' => $collection->receipt_number,
                    'quantity' => null, 'unitPrice' => null,
                    'signedAmount' => Money::format(-$collected),
                    'runningBalance' => Money::format($running),
                ];
            }
            $after['closingBalance'] = Money::format($running);
            // Reconciliation fields in the baseline report describe the period
            // BEFORE the later settlement movements. They remain historical.
            DB::table('finance_customer_settlements')->where('id', $id)->update([
                'balance_after' => $after['closingBalance'],
                'collection_id' => $collectionId,
                'statement_snapshot' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $this->idempotency->finish($claim, 'settlement', $id);

            return $this->show($customerId, $id);
        }, 3);
    }

    private function summary(object $record): array
    {
        $balance = Money::cents((string) $record->balance_after);
        $collected = Money::cents((string) $record->collected_amount);
        $discount = Money::cents((string) $record->discount_amount);

        return [
            'id' => (int) $record->id,
            'number' => 'SET-'.substr((string) $record->created_at, 0, 4).'-'.str_pad((string) $record->id, 5, '0', STR_PAD_LEFT),
            'dateFrom' => (string) $record->date_from, 'dateTo' => (string) $record->date_to,
            'settledOn' => (string) $record->settled_on,
            'createdAt' => (string) $record->created_at,
            'balanceBefore' => Money::format(Money::cents((string) $record->balance_before)),
            'balanceAfter' => Money::format($balance),
            'discountAmount' => Money::format($discount),
            'collectedAmount' => Money::format($collected),
            // A reversed receipt changes today's balance, not the issued snapshot.
            'collectionReversed' => (int) ($record->collection_status ?? 0) === CollectionStatus::REVERSED->value,
            'status' => $balance < 0 ? 'credit' : ($balance === 0 ? 'settled'
                : ($collected > 0 || $discount > 0 ? 'partial' : 'unpaid')),
        ];
    }
}
