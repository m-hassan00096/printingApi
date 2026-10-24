<?php
namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Finance\Http\Requests\LedgerIndexRequest;
use Modules\Finance\Http\Requests\StoreOpeningBalanceRequest;
use Modules\Finance\Actions\CreateOpeningBalanceAction;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Services\LedgerService;
use Modules\Finance\Support\Money;

class CustomerFinanceController extends Controller
{
    public function balance(int $customer, LedgerService $ledger): JsonResponse
    {
        $this->assertCustomer($customer);
        return ApiResponse::success(data: ['customerId' => $customer] + $ledger->balance($customer));
    }

    public function ledger(int $customer, LedgerIndexRequest $request, LedgerService $ledger): JsonResponse
    {
        $this->assertCustomer($customer);
        $filters = $request->validated('filter', []);
        $query = LedgerEntry::query()->where('customer_id', $customer);
        if (isset($filters['dateFrom'])) $query->whereDate('effective_date', '>=', $filters['dateFrom']);
        if (isset($filters['dateTo'])) $query->whereDate('effective_date', '<=', $filters['dateTo']);
        if (isset($filters['type'])) $query->where('type', $filters['type']);
        $pagination = $query->orderByDesc('effective_date')->orderByDesc('id')
            ->paginate((int) $request->input('perPage', 20));
        return ApiResponse::success(data: [
            'entries' => $pagination->getCollection()->map(fn (LedgerEntry $entry): array => [
                'id' => $entry->id, 'type' => $entry->type->value, 'typeLabel' => $entry->type->label(),
                'amount' => Money::format(abs(Money::cents((string) $entry->signed_amount))),
                'signedAmount' => (string) $entry->signed_amount,
                'orderId' => $entry->order_id, 'collectionId' => $entry->collection_id,
                'refundId' => $entry->refund_id, 'effectiveDate' => $entry->effective_date->format('Y-m-d'),
                'note' => $entry->note, 'createdAt' => $entry->created_at?->toIso8601String(),
            ])->all(),
            'balance' => $ledger->balance($customer),
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
