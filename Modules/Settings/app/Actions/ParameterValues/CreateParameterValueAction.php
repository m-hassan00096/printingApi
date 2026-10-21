<?php

namespace Modules\Settings\Actions\ParameterValues;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;
use Modules\Settings\Models\ParameterValue;

class CreateParameterValueAction
{
    public function execute(int $parameterId, array $data): ParameterValue
    {
        $parameter = Parameter::query()->findOrFail($parameterId);

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
                'required',
                'string',
                'max:150',
                Rule::unique('parameter_values', 'name')
                    ->where('parameter_id', $parameter->getKey()),
            ],
            'description' => ['nullable', 'string'],
            'status' => [
                'bail',
                'required',
                'integer',
                Rule::enum(ParameterValueStatus::class),
            ],
            'parameterId' => ['prohibited'],
            'parameter_id' => ['prohibited'],
        ])->validate();

        // Creating through the relationship sets the FK without exposing it as fillable.
        return $parameter->values()->create($validated);
    }
}
