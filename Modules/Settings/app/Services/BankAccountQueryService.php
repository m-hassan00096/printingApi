<?php

namespace Modules\Settings\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Settings\Http\Requests\PaymentDestinations\IndexPaymentDestinationsRequest;
use Modules\Settings\Models\BankAccount;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class BankAccountQueryService
{
    public function paginate(IndexPaymentDestinationsRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(BankAccount::query(), $request)
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = is_array($value) ? implode(',', $value) : (string) $value;

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('bank_name', 'like', "%{$search}%")
                            ->orWhere('account_number', 'like', "%{$search}%");
                    });
                }),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(...['id', AllowedSort::field('bankName', 'bank_name'), 'created_at'])
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }
}
