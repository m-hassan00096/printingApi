<?php
namespace Modules\Orders\Services;

use Carbon\CarbonImmutable;
use Modules\Orders\Enums\OrderDelayStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

class OrderDelayCalculator
{
    public function calculate(Order $order): ?OrderDelayStatus
    {
        if ($order->status === OrderStatus::DRAFT) {
            return null;
        }

        if ($order->due_date === null) {
            return OrderDelayStatus::NO_DUE_DATE;
        }

        $dueDay = $order->due_date->format('Y-m-d');
        if ($order->delivered_at !== null && $order->delivered_local_date !== null) {
            // Delivery saves Cairo's local date on the same transaction as delivered_at.
            $day = $order->delivered_local_date->format('Y-m-d');
        } else {
            $reference = $order->delivered_at ?? $order->cancelled_at;
            $day = $reference === null
                ? CarbonImmutable::now('Africa/Cairo')->toDateString()
                : CarbonImmutable::instance($reference)->timezone('Africa/Cairo')->toDateString();
        }

        return $day > $dueDay ? OrderDelayStatus::LATE : OrderDelayStatus::ON_TIME;
    }
}
