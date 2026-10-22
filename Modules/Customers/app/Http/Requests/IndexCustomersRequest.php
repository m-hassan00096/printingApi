<?php
namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Http\Requests\Concerns\LocalizedCustomerValidation;

class IndexCustomersRequest extends FormRequest
{
    use LocalizedCustomerValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,customerTypeId,status'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.customerTypeId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'filter.status' => ['sometimes', 'nullable', 'integer', Rule::enum(CustomerStatus::class)],
            'sort' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
