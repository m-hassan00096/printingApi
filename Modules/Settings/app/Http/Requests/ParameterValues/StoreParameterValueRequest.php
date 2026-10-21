<?php

namespace Modules\Settings\Http\Requests\ParameterValues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;

class StoreParameterValueRequest extends FormRequest
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
        $isPrintingLocation = Parameter::query()
            ->whereKey($this->integer('parameterId'))
            ->where('name', 'printing_locations')
            ->exists();

        return [
            'parameterId' => [
                'required',
                'integer',
                Rule::exists('parameters', 'id'),
            ],
            'parameter_id' => ['prohibited'],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('parameter_values', 'name')
                    ->where('parameter_id', $this->input('parameterId')),
            ],
            'description' => ['nullable', 'string', Rule::prohibitedIf($isPrintingLocation)],
            'status' => [
                'required',
                'integer',
                Rule::enum(ParameterValueStatus::class),
            ],
        ];
    }
}
