<?php

namespace Modules\Settings\Actions\ParameterValues;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\ParameterValueStatus;
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

            $input = $data;

            if (is_string($input['name'] ?? null)) {
                $input['name'] = trim($input['name']);
            }

            if (($input['status'] ?? null) instanceof ParameterValueStatus) {
                $input['status'] = $input['status']->value;
            }

            $validated = Validator::make($input, [
                'name' => [
                    'bail',
                    'sometimes',
                    'required',
                    'string',
                    'max:150',
                    Rule::unique('parameter_values', 'name')
                        ->where('parameter_id', $value->parameter_id)
                        ->ignore($value->getKey()),
                ],
                'description' => ['sometimes', 'nullable', 'string'],
                'status' => [
                    'bail',
                    'sometimes',
                    'required',
                    'integer',
                    Rule::enum(ParameterValueStatus::class),
                ],
                'parameterId' => ['prohibited'],
                'parameter_id' => ['prohibited'],
            ])->validate();

            if ($validated !== []) {
                $value->fill($validated);
                $value->save();
            }

            return $value->refresh();
        });
    }
}
