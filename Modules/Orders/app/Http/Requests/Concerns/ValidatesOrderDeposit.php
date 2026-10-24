<?php
namespace Modules\Orders\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Settings\Contracts\PaymentDestinationLookup;

trait ValidatesOrderDeposit
{
    /** HTTP validation only; financial posting remains inside Finance. */
    protected function depositRules(bool $confirmation): array
    {
        $hasPayment = $confirmation && $this->hasPositiveDeposit();
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

    /** @return array<callable> */
    protected function depositValidationCallbacks(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasPositiveDeposit() || $validator->errors()->has('paymentMethod')
                    || $validator->errors()->has('paymentDestinationId')) {
                    return;
                }
                $method = $this->input('paymentMethod');
                if ($method === null) {
                    return;
                }
                $destination = $this->input('paymentDestinationId');
                if (! app(PaymentDestinationLookup::class)->available(
                    (int) $method, $destination === null ? null : (int) $destination,
                )) {
                    $validator->errors()->add('paymentDestinationId',
                        __('validation.exists', ['attribute' => 'paymentDestinationId']));
                }
            },
        ];
    }

    private function hasPositiveDeposit(): bool
    {
        $amount = $this->input('depositAmount');
        return is_string($amount)
            && preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', $amount) === 1
            && preg_match('/[1-9]/', $amount) === 1;
    }
}
