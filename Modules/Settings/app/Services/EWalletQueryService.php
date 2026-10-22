<?php

namespace Modules\Settings\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Settings\Http\Requests\PaymentDestinations\IndexPaymentDestinationsRequest;
use Modules\Settings\Models\EWallet;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EWalletQueryService
{
    public function paginate(IndexPaymentDestinationsRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(EWallet::query(), $request)
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = is_array($value) ? implode(',', $value) : (string) $value;

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('account_holder', 'like', "%{$search}%");
                    });
                }),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(...['id', 'name', 'created_at'])
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }
}
