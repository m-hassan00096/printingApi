<?php
namespace Modules\Orders\Services;

use Illuminate\Validation\ValidationException;
use Modules\Orders\Contracts\OrderFinanceReference;
use Modules\Orders\Models\Order;

class OrderFinanceReferenceService implements OrderFinanceReference
{
    public function assertCustomerOwnsOrder(int $orderId, int $customerId): void
    {
        if (! Order::query()->whereKey($orderId)->where('customer_id', $customerId)->exists()) {
            throw ValidationException::withMessages(['orderId' => __('validation.exists', ['attribute' => 'orderId'])]);
        }
    }

    public function findOrderIdsByNumber(string $search): array
    {
        return Order::withTrashed()->where('order_number', 'like', '%'.$search.'%')->pluck('id')->all();
    }
}
