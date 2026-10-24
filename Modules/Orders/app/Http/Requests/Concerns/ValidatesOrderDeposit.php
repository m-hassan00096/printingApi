<?php
namespace Modules\Orders\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesOrderDeposit
{
    /** Called from Store/Update form requests; confirmation=true only when status=1. */
    protected function depositRules(bool $confirmation): array
    {
        $amount = $this->input('depositAmount');
        $hasPayment = $confirmation && is_string($amount) && (float) $amount > 0;
        $method = $this->input('paymentMethod');

        return [
            'depositAmount' => [
                'sometimes', 'nullable', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
                Rule::prohibitedIf(! $confirmation),
            ],
            'paymentMethod' => [
                Rule::requiredIf($hasPayment), 'nullable', 'integer', 'in:0,1,2',
                Rule::prohibitedIf(! $hasPayment),
            ],
            'paymentDestinationId' => [
                Rule::requiredIf($hasPayment && in_array((string) $method, ['1', '2'], true)),
                'nullable', 'integer', 'min:1',
                Rule::prohibitedIf(! $hasPayment || (string) $method === '0'),
            ],
        ];
    }
}
