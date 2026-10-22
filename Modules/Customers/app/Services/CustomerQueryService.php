<?php
namespace Modules\Customers\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Customers\Http\Requests\IndexCustomersRequest;
use Modules\Customers\Models\Customer;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerQueryService
{
    public function paginate(IndexCustomersRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Customer::query()->with('customerType'), $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $input = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $value);
                    $like = '%'.$input.'%';
                    $query->where(function (Builder $query) use ($like): void {
                        $query->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$like]);
                    });
                }),
                AllowedFilter::exact('customerTypeId', 'customer_type_id'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('id', 'name', 'created_at')
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }
}
