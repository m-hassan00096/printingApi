<?php

namespace Modules\Settings\Contracts;

use Modules\Settings\Models\ParameterValue;

/**
 * Each owning module implements its own reference check.
 * Settings must not import the implementation or know the referencing tables.
 */
interface ParameterValueUsageGuard
{
    public function assertCanDelete(ParameterValue $value): void;
}

