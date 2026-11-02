<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Finance\Enums\PaymentMethod;
use Modules\Finance\Support\Money;
use Modules\Settings\Contracts\PaymentDestinationLookup;

class StoreCustomerSettlementRequest extends FinanceWriteRequest
{
    public function rules(): array
    {
        return [
            'dateFrom' => ['required', 'date_format:Y-m-d'],
            'dateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:dateFrom',
                'before_or_equal:'.now('Africa/Cairo')->toDateString()],
            'discountAmount' => ['required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D'],
            'discountReason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'collectedAmount' => ['required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D'],
            'paymentMethod' => ['sometimes', 'nullable', 'integer', Rule::enum(PaymentMethod::class)],
            'paymentDestinationId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->has('discountAmount') || $validator->errors()->has('collectedAmount')) {
                    return;
                }
                $discount = Money::cents((string) $this->input('discountAmount'));
                $collected = Money::cents((string) $this->input('collectedAmount'));

                if ($discount > 0 && mb_strlen(trim((string) $this->input('discountReason'))) < 3) {
                    $validator->errors()->add('discountReason', __('finance.messages.settlement_discount_reason_required'));
                }
                if ($collected === 0) {
                    if ($this->filled('paymentMethod') || $this->filled('paymentDestinationId')) {
                        $validator->errors()->add('paymentMethod', __('finance.messages.settlement_payment_without_collection'));
                    }
                    return;
                }

                if (! $this->filled('paymentMethod') || $validator->errors()->has('paymentMethod')) {
                    $validator->errors()->add('paymentMethod', __('validation.required', ['attribute' => 'paymentMethod']));
                    return;
                }
                $method = (int) $this->input('paymentMethod');
                $destination = $this->input('paymentDestinationId');
                if ($method === 0 && $destination !== null) {
                    $validator->errors()->add('paymentDestinationId',
                        __('validation.prohibited', ['attribute' => 'paymentDestinationId']));
                    return;
                }
                if (in_array($method, [1, 2], true) && $destination === null) {
                    $validator->errors()->add('paymentDestinationId',
                        __('validation.required', ['attribute' => 'paymentDestinationId']));
                    return;
                }
                if (! $validator->errors()->has('paymentDestinationId')
                    && ! app(PaymentDestinationLookup::class)->available($method,
                        $destination === null ? null : (int) $destination)) {
                    $validator->errors()->add('paymentDestinationId',
                        __('validation.exists', ['attribute' => 'paymentDestinationId']));
                }
            },
        ];
    }
}
