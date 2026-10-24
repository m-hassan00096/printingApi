<?php
namespace Modules\Finance\Http\Requests;

class StoreRefundRequest extends FinanceWriteRequest
{
    public function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
                'not_in:0,0.0,0.00'],
            ...$this->paymentRules(),
            'reason' => ['required', 'string', 'min:3', 'max:3000'],
        ];
    }
}
