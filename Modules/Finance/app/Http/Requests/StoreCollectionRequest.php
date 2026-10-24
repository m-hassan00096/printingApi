<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Validator;
use Modules\Orders\Contracts\OrderFinanceReference;

class StoreCollectionRequest extends FinanceWriteRequest
{
    public function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
                'not_in:0,0.0,0.00'],
            ...$this->paymentRules(),
            'orderId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'collectedOn' => ['sometimes', 'date_format:Y-m-d',
                'before_or_equal:'.now('Africa/Cairo')->toDateString()],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->has('orderId') || $validator->errors()->has('customerId')
                    || ! $this->filled('orderId')) {
                    return;
                }
                if (! app(OrderFinanceReference::class)->belongsToCustomer(
                    (int) $this->input('orderId'), (int) $this->input('customerId'))) {
                    $validator->errors()->add('orderId', __('validation.exists', ['attribute' => 'orderId']));
                }
            },
        ];
    }
}
