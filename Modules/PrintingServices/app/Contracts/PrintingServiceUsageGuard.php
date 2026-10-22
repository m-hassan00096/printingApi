<?php

namespace Modules\PrintingServices\Contracts;

use Modules\PrintingServices\Models\PrintingService;

/**
 * The referencing module (future Orders) decides whether a service is in use.
 * This contract does not know Orders' database tables or lifecycle states.
 */
interface PrintingServiceUsageGuard
{
    public function assertCanDelete(PrintingService $service): void;
}

