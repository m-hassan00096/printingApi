<?php

namespace Modules\Settings\Actions\ParameterValues;

use Illuminate\Support\Facades\DB;
use Modules\Settings\Models\ParameterValue;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Services\ParameterValueUsageRegistry;

class UpdateParameterValueAction
{
    public function __construct(
        private readonly ParameterValueUsageRegistry $usageRegistry,
    ) {}

    public function execute(ParameterValue $parameterValue, array $data): ParameterValue
    {
        return DB::transaction(function () use ($parameterValue, $data): ParameterValue {
            $value = ParameterValue::query()
                ->whereKey($parameterValue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Check only the ACTIVE -> INACTIVE transition, while the row is
            // locked. Each future consumer (not Settings) registers its guard.
            if ($value->status === ParameterValueStatus::ACTIVE
                && array_key_exists('status', $data)
                && (int) $data['status'] === ParameterValueStatus::INACTIVE->value) {
                $this->usageRegistry->assertCanDeactivate($value);
            }

            // parameter_id is intentionally not fillable and cannot be reassigned.
            $value->fill($data);
            $value->save();

            return $value->refresh();
        });
    }
}
