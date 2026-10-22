<?php
namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Http\Requests\Concerns\LocalizedCustomerValidation;
use Modules\Customers\Http\Requests\Concerns\ValidatesCustomerFields;

class StoreCustomerRequest extends FormRequest
{
    use LocalizedCustomerValidation, ValidatesCustomerFields;

    protected function prepareForValidation(): void
    {
        $this->prepareCustomerFields();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:100', $this->uniquePhoneRule()],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'customerTypeId' => ['required', 'integer', $this->customerTypeRule()],
            'status' => ['required', 'integer', Rule::enum(CustomerStatus::class)],
        ];
    }
}
