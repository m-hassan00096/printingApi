<?php

namespace Modules\Settings\Contracts;

use Modules\Settings\Models\ParameterValue;

/**
 * Owning modules may block a live parameter value from becoming inactive.
 * No Settings dependency on Customers or other consumers is introduced.
 */
interface ParameterValueDeactivationGuard
{
    public function assertCanDeactivate(ParameterValue $value): void;
}

