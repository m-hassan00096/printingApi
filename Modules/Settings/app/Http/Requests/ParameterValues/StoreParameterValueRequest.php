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
            'parameterId' => ['required', 'integer', Rule::exists('parameters', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'integer', Rule::enum(ParameterValueStatus::class)],
        ];
    }
}
