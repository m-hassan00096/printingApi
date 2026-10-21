<?php

namespace Modules\Settings\Http\Requests\BankAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Modules\Settings\Models\BankAccount;

class UpdateBankAccountRequest extends FormRequest
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
        /** @var BankAccount $bankAccount */
        $bankAccount = $this->route('bankAccount');

        return [
            'bankName' => ['sometimes', 'required', 'string', 'max:150'],
            'accountNumber' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('bank_accounts', 'account_number')->ignore($bankAccount),
            ],
            'isDefault' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'required', 'integer', Rule::enum(PaymentDestinationStatus::class)],
            'bank_name' => ['prohibited'],
            'account_number' => ['prohibited'],
            'is_default' => ['prohibited'],
        ];
    }
}
