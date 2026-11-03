<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Finance\Enums\PaymentMethod;

class CreateAccountSettlementRequest extends FinanceWriteRequest
{
    public function rules(): array
    {
        $amount = $this->input('amount');
        $discount = $this->input('discountAmount');
        $discounting = is_string($discount) && (bool) preg_match('/[1-9]/', $discount);
        $paying = is_string($amount) && (bool) preg_match('/[1-9]/', $amount);
        $method = $this->input('paymentMethod');
        $nonCash = (is_int($method) || is_string($method)) && in_array((string) $method, ['1', '2'], true);
        return [
            'dateFrom' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:dateTo'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Africa/Cairo')->toDateString()],
            'previewHash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
            'amount' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D'],
            'discountAmount' => ['sometimes', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D'],
            'discountReason' => [Rule::requiredIf($discounting), 'nullable', 'string', 'min:3', 'max:1000'],
            'paymentMethod' => [Rule::requiredIf($paying), Rule::prohibitedIf(! $paying), 'integer', Rule::enum(PaymentMethod::class)],
            'paymentDestinationId' => [Rule::requiredIf($paying && $nonCash), Rule::prohibitedIf(! $paying || ! $nonCash), 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
