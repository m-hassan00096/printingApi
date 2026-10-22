<?php

namespace Modules\Settings\Services;

use InvalidArgumentException;
use Modules\Settings\Contracts\ParameterValueUsageGuard;
use Modules\Settings\Models\ParameterValue;

final class ParameterValueUsageRegistry
{
    /** @var array<class-string<ParameterValueUsageGuard>, true> */
    private array $guards = [];

    /** @param class-string<ParameterValueUsageGuard> $guardClass */
    public function register(string $guardClass): void
    {
        if (! is_a($guardClass, ParameterValueUsageGuard::class, true)) {
            throw new InvalidArgumentException("Invalid parameter value usage guard [{$guardClass}].");
        }

        // Idempotent even if provider boot() runs more than once in a test.
        $this->guards[$guardClass] = true;
    }

    public function assertCanDelete(ParameterValue $value): void
    {
        foreach (array_keys($this->guards) as $guardClass) {
            // Resolve on demand: supports each module's injected dependencies.
            app($guardClass)->assertCanDelete($value);
        }
    }
}

