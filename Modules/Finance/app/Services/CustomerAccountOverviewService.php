<?php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Enums\BalanceType;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Http\Requests\CustomerBalancesIndexRequest;
use Modules\Finance\Support\Money;

class CustomerAccountOverviewService
{
    public function index(CustomerBalancesIndexRequest $request): array
    {
        // A single grouped ledger scan, not one balance query per customer.
        $totals = DB::table('finance_ledger_entries')
            ->select('customer_id')
            ->selectRaw('SUM(signed_amount) AS signed_balance')
            ->selectRaw('MAX(CASE WHEN type = ? THEN 1 ELSE 0 END) AS has_opening', [LedgerEntryType::OPENING_BALANCE->value])
            ->groupBy('customer_id');

        $query = DB::table('customers')
            ->leftJoinSub($totals, 'balances', fn ($join) => $join->on('balances.customer_id', '=', 'customers.id'))
            ->whereNull('customers.deleted_at')
            ->select(['customers.id', 'customers.name', 'customers.phone'])
            ->selectRaw('COALESCE(balances.signed_balance, 0) AS signed_balance')
            ->selectRaw('COALESCE(balances.has_opening, 0) AS has_opening');

        $filters = $request->validated('filter', []);
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(fn ($q) => $q->where('customers.name', 'like', '%'.$search.'%')
                ->orWhere('customers.phone', 'like', '%'.$search.'%'));
        }
        if (isset($filters['type'])) {
            match ((int) $filters['type']) {
                1 => $query->whereRaw('COALESCE(balances.signed_balance, 0) > 0'),
                2 => $query->whereRaw('COALESCE(balances.signed_balance, 0) < 0'),
                default => $query->whereRaw('COALESCE(balances.signed_balance, 0) = 0'),
            };
        }

        $sort = $request->input('sort', '-id');
        match ($sort) {
            'id' => $query->orderBy('customers.id'),
            'name' => $query->orderBy('customers.name')->orderBy('customers.id'),
            '-name' => $query->orderByDesc('customers.name')->orderByDesc('customers.id'),
            'balance' => $query->orderByRaw('COALESCE(balances.signed_balance, 0) ASC')->orderBy('customers.id'),
            '-balance' => $query->orderByRaw('COALESCE(balances.signed_balance, 0) DESC')->orderBy('customers.id'),
            default => $query->orderByDesc('customers.id'),
        };

        $pagination = $query->paginate((int) $request->input('perPage', 20));
        $accounts = $pagination->getCollection()->map(function (object $row): array {
            $cents = Money::cents((string) $row->signed_balance);
            $type = $cents > 0 ? BalanceType::DEBIT : ($cents < 0 ? BalanceType::CREDIT : BalanceType::ZERO);
            return [
                'customerId' => (int) $row->id,
                'customerName' => $row->name,
                'customerPhone' => $row->phone,
                'amount' => Money::format(abs($cents)),
                'type' => $type->value,
                'typeLabel' => $type->label(),
                'openingBalanceExists' => (bool) $row->has_opening,
            ];
        })->all();

        return [
            'accounts' => $accounts,
            'pagination' => [
                'currentPage' => $pagination->currentPage(), 'lastPage' => $pagination->lastPage(),
                'perPage' => $pagination->perPage(), 'total' => $pagination->total(),
            ],
        ];
    }
}
