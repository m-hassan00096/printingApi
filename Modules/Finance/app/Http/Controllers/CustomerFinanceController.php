<?php
namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Finance\Http\Requests\LedgerIndexRequest;
use Modules\Finance\Http\Requests\CustomerBalancesIndexRequest;
use Modules\Finance\Http\Requests\StoreOpeningBalanceRequest;
use Modules\Finance\Actions\CreateOpeningBalanceAction;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Services\CustomerAccountOverviewService;
use Modules\Finance\Enums\LedgerEntryType;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\Finance\Services\LedgerService;
use Modules\Finance\Support\Money;

class CustomerFinanceController extends Controller
{
    public function accounts(CustomerBalancesIndexRequest $request, CustomerAccountOverviewService $overview): JsonResponse
    {
        return ApiResponse::success(data: $overview->index($request));
    }

    public function openingBalanceStatus(int $customer): JsonResponse
    {
        $this->assertCustomer($customer);
        return ApiResponse::success(data: ['customerId' => $customer, 'openingBalanceExists' => $this->hasOpening($customer)]);
    }

    private function hasOpening(int $customer): bool
    {
        return LedgerEntry::query()->where('customer_id', $customer)
            ->where('type', LedgerEntryType::OPENING_BALANCE->value)->exists();
    }

    public function balance(int $customer, LedgerService $ledger): JsonResponse
    {
        $this->assertCustomer($customer);
        return ApiResponse::success(data: ['customerId' => $customer] + $ledger->balance($customer)
            + ['openingBalanceExists' => $this->hasOpening($customer)]);
    }

    public function ledger(int $customer, LedgerIndexRequest $request, LedgerService $ledger): JsonResponse
    {
        $this->assertCustomer($customer);
        $filters = $request->validated('filter', []);
        // Evaluate the window over ALL customer movements first. Filtering a
        // completed running balance never rewrites the historical arithmetic.
        // effective_date + id is the stable posting order, even when a
        // payment is back-dated or multiple entries have the same date.
        $allRows = DB::table('finance_ledger_entries')
            ->where('customer_id', $customer)
            ->select(['id', 'type', 'signed_amount', 'order_id', 'collection_id', 'refund_id',
                'effective_date', 'note', 'created_at'])
            ->selectRaw('SUM(signed_amount) OVER (ORDER BY effective_date ASC, id ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS running_signed_amount');

        $query = DB::query()->fromSub($allRows, 'ledger_rows');
        if (isset($filters['dateFrom'])) $query->where('effective_date', '>=', $filters['dateFrom']);
        if (isset($filters['dateTo'])) $query->where('effective_date', '<=', $filters['dateTo']);
        if (isset($filters['type'])) $query->where('type', (int) $filters['type']);
        $pagination = $query->orderByDesc('effective_date')->orderByDesc('id')
            ->paginate((int) $request->input('perPage', 20));

        return ApiResponse::success(data: [
            'entries' => $pagination->getCollection()->map(function (object $entry): array {
                $runningCents = Money::cents((string) $entry->running_signed_amount);
                $runningType = $runningCents > 0 ? \Modules\Finance\Enums\BalanceType::DEBIT
                    : ($runningCents < 0 ? \Modules\Finance\Enums\BalanceType::CREDIT : \Modules\Finance\Enums\BalanceType::ZERO);
                $entryType = LedgerEntryType::from((int) $entry->type);
                return [
                    'id' => $entry->id, 'type' => $entryType->value, 'typeLabel' => $entryType->label(),
                    'amount' => Money::format(abs(Money::cents((string) $entry->signed_amount))),
                    'signedAmount' => (string) $entry->signed_amount,
                    'runningBalance' => [
                        'amount' => Money::format(abs($runningCents)), 'type' => $runningType->value,
                        'typeLabel' => $runningType->label(),
                    ],
                    'orderId' => $entry->order_id, 'collectionId' => $entry->collection_id,
                    'refundId' => $entry->refund_id,
                    'effectiveDate' => $entry->effective_date,
                    'note' => $entry->note,
                    'createdAt' => $entry->created_at ? Carbon::parse($entry->created_at)->toIso8601String() : null,
                ];
            })->all(),
            'balance' => $ledger->balance($customer),
            'openingBalanceExists' => $this->hasOpening($customer),
            'pagination' => [
                'currentPage' => $pagination->currentPage(), 'lastPage' => $pagination->lastPage(),
                'perPage' => $pagination->perPage(), 'total' => $pagination->total(),
            ],
        ]);
    }

    public function openingBalance(int $customer, StoreOpeningBalanceRequest $request, CreateOpeningBalanceAction $action): JsonResponse
    {
        $action->execute($customer, $request->validated());
        return ApiResponse::success(message: __('finance.messages.opening_created'), status: HttpStatusCode::CREATED);
    }

    private function assertCustomer(int $customer): void
    {
        app(\Modules\Customers\Contracts\CustomerFinanceReference::class)->display($customer);
    }
}
