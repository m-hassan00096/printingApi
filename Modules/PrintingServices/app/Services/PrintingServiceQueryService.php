<?php

namespace Modules\PrintingServices\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\PrintingServices\Http\Requests\IndexPrintingServicesRequest;
use Modules\PrintingServices\Models\PrintingService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PrintingServiceQueryService
{
    public function paginate(IndexPrintingServicesRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(PrintingService::query(), $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.(string) $value.'%');
                }),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('id', 'name', 'created_at')
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }
}

