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
        return Order::withTrashed()->where('order_number', 'like', '%'.$search.'%')->pluck('id')->all();
    }
}
