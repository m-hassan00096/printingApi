<?php

namespace Modules\Settings\Http\Requests\PaymentDestinations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\PaymentDestinationStatus;

class IndexPaymentDestinationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,status'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.status' => ['sometimes', 'nullable', Rule::in([
                PaymentDestinationStatus::INACTIVE->value,
                PaymentDestinationStatus::ACTIVE->value,
            ])],
            'sort' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
