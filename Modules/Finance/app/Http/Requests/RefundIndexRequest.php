<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Finance\Enums\PaymentMethod;

class RefundIndexRequest extends FinanceIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:200'],
            'customerId' => ['sometimes', 'integer', 'min:1'],
            'dateFrom' => ['sometimes', 'date_format:Y-m-d'],
            'dateTo' => ['sometimes', 'date_format:Y-m-d'],
            'paymentMethod' => ['sometimes', 'integer', Rule::enum(PaymentMethod::class)],
        ];
    }
}
