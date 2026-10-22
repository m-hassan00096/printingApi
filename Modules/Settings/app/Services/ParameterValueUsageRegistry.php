<?php

namespace Modules\Settings\Services;

use InvalidArgumentException;
use Modules\Settings\Contracts\ParameterValueUsageGuard;
use Modules\Settings\Contracts\ParameterValueDeactivationGuard;
use Modules\Settings\Models\ParameterValue;

final class ParameterValueUsageRegistry
{
    /** @var array<class-string<ParameterValueUsageGuard>, true> */
    private array $guards = [];

    /** @var array<class-string<ParameterValueDeactivationGuard>, true> */
    private array $deactivationGuards = [];

    /** @param class-string<ParameterValueUsageGuard> $guardClass */
    public function register(string $guardClass): void
    {
        if (! is_a($guardClass, ParameterValueUsageGuard::class, true)) {
            throw new InvalidArgumentException(
                __('settings.invalid_parameter_value_usage_guard', ['guard' => $guardClass])
            );
        }

        // Idempotent even if provider boot() runs more than once in a test.
        $this->guards[$guardClass] = true;
    }

    /** @param class-string<ParameterValueDeactivationGuard> $guardClass */
    public function registerDeactivationGuard(string $guardClass): void
    {
        if (! is_a($guardClass, ParameterValueDeactivationGuard::class, true)) {
            throw new InvalidArgumentException(
                __('settings.invalid_parameter_value_deactivation_guard', ['guard' => $guardClass])
            );
        }

        $this->deactivationGuards[$guardClass] = true;
    }

    public function assertCanDeactivate(ParameterValue $value): void
    {
        foreach (array_keys($this->deactivationGuards) as $guardClass) {
            app($guardClass)->assertCanDeactivate($value);
        }
    }

    public function assertCanDelete(ParameterValue $value): void
    {
        foreach (array_keys($this->guards) as $guardClass) {
            // Resolve on demand: supports each module's injected dependencies.
            app($guardClass)->assertCanDelete($value);
        }
    }
}

