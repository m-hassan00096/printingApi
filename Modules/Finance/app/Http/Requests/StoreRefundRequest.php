<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Enums\PaymentMethod;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', 'not_in:0,0.0,0.00'],
            'paymentMethod' => ['required', 'integer', Rule::enum(PaymentMethod::class)],
            'paymentDestinationId' => [
                Rule::requiredIf(fn () => in_array((int) $this->input('paymentMethod'), [1, 2], true)),
                'nullable', 'integer', 'min:1', Rule::prohibitedIf(fn () => (string) $this->input('paymentMethod') === '0'),
            ],
            'reason' => ['required', 'string', 'min:3', 'max:3000'],
        ];
    }
}
