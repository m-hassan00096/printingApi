<?php
namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Http\Requests\Concerns\LocalizedCustomerValidation;
use Modules\Customers\Http\Requests\Concerns\ValidatesCustomerFields;
use Modules\Customers\Models\Customer;

class UpdateCustomerRequest extends FormRequest
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
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'required', 'string', 'max:100', $this->uniquePhoneRule($customer?->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'customerTypeId' => ['sometimes', 'required', 'integer', $this->customerTypeRule()],
            'status' => ['sometimes', 'required', 'integer', Rule::enum(CustomerStatus::class)],
        ];
    }
}
