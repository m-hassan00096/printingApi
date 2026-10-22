<?php

namespace Modules\PrintingServices\Services;

use InvalidArgumentException;
use Modules\PrintingServices\Contracts\PrintingServiceUsageGuard;
use Modules\PrintingServices\Models\PrintingService;

final class PrintingServiceUsageRegistry
{
    /** @var array<class-string<PrintingServiceUsageGuard>, true> */
    private array $guards = [];

    /** @param class-string<PrintingServiceUsageGuard> $guardClass */
    public function register(string $guardClass): void
    {
        if (! is_a($guardClass, PrintingServiceUsageGuard::class, true)) {
            throw new InvalidArgumentException(
                __('printing_services.messages.invalid_service_usage_guard', [
                    'guard' => $guardClass,
                ])
            );
        }

        $this->guards[$guardClass] = true;
    }

    public function assertCanDelete(PrintingService $service): void
    {
        foreach (array_keys($this->guards) as $guardClass) {
            app($guardClass)->assertCanDelete($service);
        }
    }
}

