<?php

namespace Modules\PrintingServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PricePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serviceId' => ['required', 'integer', Rule::exists('printing_services', 'id')->whereNull('deleted_at')],
            'customerTypeId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ];
    }
}

