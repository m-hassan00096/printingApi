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
                // Dates use Cairo local calendar days, while created_at is stored as UTC.
                // Inclusive from, exclusive start of the day after to (DST-safe).
                AllowedFilter::callback('createdFrom', function (Builder $query, mixed $value): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $from = CarbonImmutable::parse((string) $value, 'Africa/Cairo')
                        ->startOfDay()->utc();
                    $query->where('created_at', '>=', $from);
                }),
                AllowedFilter::callback('createdTo', function (Builder $query, mixed $value): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $until = CarbonImmutable::parse((string) $value, 'Africa/Cairo')
                        ->addDay()->startOfDay()->utc();
                    $query->where('created_at', '<', $until);
                }),
                // Legacy compatibility: the UI now uses lateOnly without openOnly.
                AllowedFilter::callback('openOnly', function (Builder $query, mixed $value): void {
                    if ((string) $value === '1') {
                        $query->where('status', OrderStatus::CONFIRMED->value)->whereNull('delivered_at');
                    }
                }),
                AllowedFilter::exact('productionStatus', 'production_status'),
                // Current overdue work only: any production stage, never delivered/canceled/draft.
                AllowedFilter::callback('lateOnly', function (Builder $query, mixed $value): void {
                    if ((string) $value !== '1') {
                        return;
                    }
                    $today = CarbonImmutable::now('Africa/Cairo')->toDateString();
                    $query->where('status', OrderStatus::CONFIRMED->value)
                        ->whereNull('delivered_at')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', $today);
                }),
                // Today's ready orders: same exact predicate used by the dashboard overview.
                AllowedFilter::callback('readyDueToday', function (Builder $query, mixed $value): void {
                    if ((string) $value !== '1') {
                        return;
                    }
                    $today = CarbonImmutable::now('Africa/Cairo')->toDateString();
                    $query->where('status', OrderStatus::CONFIRMED->value)
                        ->whereNull('delivered_at')
                        ->where('production_status', \Modules\Orders\Enums\OrderProductionStatus::READY_FOR_DELIVERY->value)
                        ->whereDate('due_date', '=', $today);
                }),
            )
            ->allowedSorts('id', 'created_at', 'due_date')
            ->defaultSort('-id')
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();
    }
}
