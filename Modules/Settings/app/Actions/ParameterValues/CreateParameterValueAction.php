<?php

namespace Modules\Settings\Actions\ParameterValues;

use Modules\Settings\Models\Parameter;
use Modules\Settings\Models\ParameterValue;

class CreateParameterValueAction
{
    public function execute(int $parameterId, array $data): ParameterValue
    {
        $parameter = Parameter::query()->findOrFail($parameterId);

        // The relationship assigns parameter_id without exposing it to mass assignment.
        return $parameter->values()->create($data);
    }
}
