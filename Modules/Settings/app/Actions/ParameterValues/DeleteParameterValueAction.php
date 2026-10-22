<?php

namespace Modules\Settings\Actions\ParameterValues;

use Illuminate\Support\Facades\DB;
use Modules\Settings\Models\ParameterValue;

class DeleteParameterValueAction
{
    public function execute(ParameterValue $parameterValue): void
    {
        DB::transaction(function () use ($parameterValue): void {
            // Do not trust a previously resolved model: the record could have
            // been soft-deleted by another request before this action runs.
            $value = ParameterValue::query()
                ->whereKey($parameterValue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Soft-deleted names stay reserved within their parameter group.
            $value->delete();
        });
    }
}
