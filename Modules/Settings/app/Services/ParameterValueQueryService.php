<?php

namespace Modules\Settings\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Settings\Http\Requests\ParameterValues\IndexParameterValuesRequest;
use Modules\Settings\Models\ParameterValue;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ParameterValueQueryService
{
    public function paginate(IndexParameterValuesRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(
            ParameterValue::query()
                ->where('parameter_id', $request->integer('parameterId')),
            $request,
        )
            ->allowedFilters(...[
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $search = is_array($value) ? implode(',', $value) : (string) $value;

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(...['id', 'name', 'created_at'])
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }

    public function find(ParameterValue $parameterValue): ParameterValue
    {
        return $parameterValue;
    }
}
