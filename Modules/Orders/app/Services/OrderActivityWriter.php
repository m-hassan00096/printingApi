<?php
namespace Modules\Orders\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Orders\Models\Order;

class OrderActivityWriter
{
    public function record(Order $order, string $type, array $context = []): void
    {
        $order->activities()->create([
            'actor_id' => Auth::id(),
            'event_type' => $type,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }
}
