<?php

namespace Modules\Settings\Http\Requests\BankAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\PaymentDestinationStatus;

class StoreBankAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['bankName', 'accountNumber'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bankName' => ['required', 'string', 'max:150'],
            'accountNumber' => ['required', 'string', 'max:50', Rule::unique('bank_accounts', 'account_number')],
            'isDefault' => ['sometimes', 'boolean'],
            'status' => ['required', 'integer', Rule::enum(PaymentDestinationStatus::class)],
            'bank_name' => ['prohibited'],
            'account_number' => ['prohibited'],
            'is_default' => ['prohibited'],
        ];
    }
}
