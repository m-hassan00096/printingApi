<?php
namespace Modules\Orders\Services;

use Modules\Orders\Contracts\OrderFinanceReference;
use Modules\Orders\Models\Order;

class OrderFinanceReferenceService implements OrderFinanceReference
{
    public function belongsToCustomer(int $orderId, int $customerId): bool
    {
        return Order::query()->whereKey($orderId)->where('customer_id', $customerId)->exists();
    }

    public function findOrderIdsByNumber(string $search): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
        return Order::withTrashed()->whereRaw("order_number LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
            ->pluck('id')->all();
    }
}
