<?php

namespace Modules\Settings\Http\Requests\ParameterValues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;

class UpdateParameterValueRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ParameterValue $parameterValue */
        $parameterValue = $this->route('parameterValue');

        return [
            'parameterId' => ['prohibited'],
            'parameter_id' => ['prohibited'],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('parameter_values', 'name')
                    ->where('parameter_id', $parameterValue->parameter_id)
                    ->ignore($parameterValue),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => [
                'sometimes',
                'required',
                'integer',
                Rule::enum(ParameterValueStatus::class),
            ],
        ];
    }
}
