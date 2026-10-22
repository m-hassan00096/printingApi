<?php

namespace Modules\PrintingServices\Tests\Feature\Support;

use Illuminate\Support\Facades\DB;
use Modules\PrintingServices\Contracts\PrintingServiceUsageGuard;
use Modules\PrintingServices\Exceptions\ServiceInUseException;
use Modules\PrintingServices\Models\PrintingService;

/**
 * Test-only simulation of the future Orders module adapter.
 * Historical order items are included even when soft-deleted.
 */
class FakeOrderServiceUsageGuard implements PrintingServiceUsageGuard
{
    public function assertCanDelete(PrintingService $service): void
    {
        if (DB::table('order_items')
            ->where('printing_service_id', $service->getKey())
            ->exists()) {
            throw new ServiceInUseException();
        }
    }
}

