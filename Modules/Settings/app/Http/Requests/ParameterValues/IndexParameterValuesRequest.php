<?php

namespace Modules\Settings\Http\Requests\ParameterValues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\ParameterValueStatus;

class IndexParameterValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parameterId' => ['required', 'integer', Rule::exists('parameters', 'id')],
            'filter' => ['sometimes', 'array:search,status'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.status' => [
                'sometimes',
                'nullable',
                Rule::in([
                    ParameterValueStatus::ACTIVE->value,
                    ParameterValueStatus::INACTIVE->value,
                ]),
            ],
            'sort' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
