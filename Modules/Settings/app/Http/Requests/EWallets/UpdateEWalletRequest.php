<?php

namespace Modules\Settings\Http\Requests\EWallets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Enums\DefaultStatus;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Modules\Settings\Models\EWallet;

class UpdateEWalletRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone', 'accountHolder', 'paymentLink'] as $field) {
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
        /** @var EWallet $eWallet */
        $eWallet = $this->route('eWallet');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => [
                'sometimes', 'required', 'string', 'regex:/\\A01[0125][0-9]{8}\\z/D',
                Rule::unique('e_wallets', 'phone')->ignore($eWallet),
            ],
            'accountHolder' => ['sometimes', 'required', 'string', 'max:150'],
            'paymentLink' => ['sometimes', 'nullable', 'string'],
            'isDefault' => ['sometimes', 'numeric', 'integer', Rule::enum(DefaultStatus::class)],
            'status' => ['sometimes', 'required', 'integer', Rule::enum(PaymentDestinationStatus::class)],
            'account_holder' => ['prohibited'],
            'payment_link' => ['prohibited'],
            'is_default' => ['prohibited'],
        ];
    }
}
