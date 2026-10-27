<?php
namespace Modules\Orders\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Http\Requests\IndexOrdersRequest;
use Modules\Orders\Models\Order;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrderQueryService
{
    public function paginate(IndexOrdersRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Order::query()->with('customer')->withCount('items'), $request)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $text = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $value);
                    $like = '%'.$text.'%';
                    $query->where(function (Builder $query) use ($like): void {
                        $query->whereRaw("order_number LIKE ? ESCAPE '!'", [$like])
                            ->orWhereHas('customer', function (Builder $customer) use ($like): void {
                                $customer->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$like]);
                            })
                            ->orWhereRaw("customer_name_snapshot LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("customer_phone_snapshot LIKE ? ESCAPE '!'", [$like]);
                    });
                }),
                AllowedFilter::exact('status'),
                // Combine with lateOnly for current overdue orders. Historical late deliveries remain available via lateOnly alone.
                AllowedFilter::callback('openOnly', function (Builder $query, mixed $value): void {
                    if ((string) $value === '1') {
                        $query->where('status', OrderStatus::CONFIRMED->value)->whereNull('delivered_at');
                    }
                }),
                AllowedFilter::exact('productionStatus', 'production_status'),
                AllowedFilter::callback('lateOnly', function (Builder $query, mixed $value): void {
                    if ((string) $value !== '1') {
                        return;
                    }
                    $today = CarbonImmutable::now('Africa/Cairo')->toDateString();
                    $query->where('status', OrderStatus::CONFIRMED->value)
                        ->whereNotNull('due_date')
                        ->where(function (Builder $query) use ($today): void {
                            $query->where(function (Builder $pending) use ($today): void {
                                $pending->whereNull('delivered_at')->whereDate('due_date', '<', $today);
                            })->orWhere(function (Builder $delivered): void {
                                $delivered->whereNotNull('delivered_at')
                                    ->whereColumn('delivered_local_date', '>', 'due_date');
                            });
                        });
                }),
            )
            ->allowedSorts('id', 'created_at', 'due_date')
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }
}
