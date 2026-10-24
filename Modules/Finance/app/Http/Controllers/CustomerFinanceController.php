<?php
namespace Modules\Finance\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Enums\BalanceType;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Http\Requests\FinanceIndexRequest;
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

    public function ledger(int $customer, FinanceIndexRequest $request, LedgerService $ledger): JsonResponse
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

    public function openingBalance(int $customer, Request $request, LedgerService $ledger): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', 'not_in:0,0.0,0.00'],
            'type' => ['required', 'integer', 'in:1,2'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($customer, $data, $ledger): void {
            $ledger->lockCustomer($customer);
            if (LedgerEntry::query()->where('event_key', 'opening:'.$customer)->exists()) {
                throw new FinanceException('opening_exists');
            }
            $cents = Money::positive($data['amount']);
            $ledger->post($customer, LedgerEntryType::OPENING_BALANCE,
                (int) $data['type'] === BalanceType::DEBIT->value ? $cents : -$cents,
                'opening:'.$customer, note: $data['note'] ?? null);
        }, 3);
        return ApiResponse::success(message: __('finance.messages.opening_created'), status: HttpStatusCode::CREATED);
    }

    private function assertCustomer(int $customer): void
    {
        app(\Modules\Customers\Contracts\CustomerFinanceReference::class)->display($customer);
    }
}
