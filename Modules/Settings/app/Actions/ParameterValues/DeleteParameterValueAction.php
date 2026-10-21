<?php

namespace Modules\Settings\Actions\ParameterValues;

use Modules\Settings\Models\ParameterValue;

class DeleteParameterValueAction
{
    public function execute(ParameterValue $parameterValue): void
    {
        // Soft-deleted records retain their names and cannot be recreated in the same group.
        $parameterValue->delete();
    }
}
