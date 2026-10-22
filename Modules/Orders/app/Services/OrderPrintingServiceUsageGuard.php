<?php
namespace Modules\Orders\Services;

use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\OrderItem;
use Modules\PrintingServices\Contracts\PrintingServiceUsageGuard;
use Modules\PrintingServices\Models\PrintingService;

class OrderPrintingServiceUsageGuard implements PrintingServiceUsageGuard
{
    public function assertCanDelete(PrintingService $service): void
    {
        // Includes Soft Deleted order-item history. Never allow deletion and reuse.
        if (OrderItem::withTrashed()->where('printing_service_id', $service->getKey())->exists()) {
            throw new OrdersBusinessException('service_in_use');
        }
    }
}
