<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Finance\Enums\PaymentMethod;

class CreateAccountSettlementRequest extends FinanceWriteRequest
{
    public function rules(): array
    {
        $amount = $this->input('amount');
        $paying = is_string($amount) && (bool) preg_match('/[1-9]/', $amount);
        $nonCash = in_array((string) $this->input('paymentMethod'), ['1', '2'], true);
        return [
            'dateTo' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Africa/Cairo')->toDateString()],
            'previewHash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
            'amount' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D'],
            'paymentMethod' => [Rule::requiredIf($paying), Rule::prohibitedIf(! $paying), 'integer', Rule::enum(PaymentMethod::class)],
            'paymentDestinationId' => [Rule::requiredIf($paying && $nonCash),
                Rule::prohibitedIf(! $paying || ! $nonCash), 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
