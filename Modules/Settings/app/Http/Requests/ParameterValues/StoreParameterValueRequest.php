<?php

namespace Modules\Settings\Http\Requests\ParameterValues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\ParameterValueStatus;

class StoreParameterValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The group is selected by the server-side route, not by request data.
            'parameterId' => ['prohibited'],
            'parameter_id' => ['prohibited'],
            'name' => ['required', 'string', 'max:150'],
            'description' => [
                'nullable',
                'string',
                Rule::prohibitedIf($this->route('parameterName') === 'printing_locations'),
            ],
            'status' => ['required', 'integer', Rule::enum(ParameterValueStatus::class)],
        ];
    }
}
