<?php

namespace Modules\Settings\Actions\ParameterValues;

use Illuminate\Support\Facades\DB;
use Modules\Settings\Models\ParameterValue;

class UpdateParameterValueAction
{
    public function execute(ParameterValue $parameterValue, array $data): ParameterValue
    {
        return DB::transaction(function () use ($parameterValue, $data): ParameterValue {
            $value = ParameterValue::query()
                ->whereKey($parameterValue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // parameter_id is intentionally not fillable and cannot be reassigned.
            $value->fill($data);
            $value->save();

            return $value->refresh();
        });
    }
}
