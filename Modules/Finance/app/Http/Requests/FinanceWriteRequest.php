<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Settings\Contracts\PaymentDestinationLookup;
use Modules\Finance\Enums\PaymentMethod;

/**
 * HTTP input concerns only. Domain invariants are rechecked under locks by Actions/Services.
 */
abstract class FinanceWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array> */
    protected function paymentRules(): array
    {
        $method = $this->input('paymentMethod');

        return [
            'paymentMethod' => ['required', 'integer', Rule::enum(PaymentMethod::class)],
            'paymentDestinationId' => [
                Rule::requiredIf(in_array((string) $method, ['1', '2'], true)),
                'nullable', 'integer', 'min:1',
                Rule::prohibitedIf((string) $method === '0'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = $this->header('Idempotency-Key');
                if (! is_string($key) || strlen($key) < 1 || strlen($key) > 160) {
                    $validator->errors()->add('Idempotency-Key', __('finance.messages.idempotency_required'));
                }

                if (! $validator->errors()->has('customerId') && $this->filled('customerId')
                    && ! app(CustomerFinanceReference::class)->exists((int) $this->input('customerId'))) {
                    $validator->errors()->add('customerId', __('validation.exists', ['attribute' => 'customerId']));
                }

                if ($validator->errors()->has('paymentMethod') || $validator->errors()->has('paymentDestinationId')) {
                    return;
                }

                $method = $this->input('paymentMethod');
                if ($method === null) {
                    return;
                }
                $destination = $this->input('paymentDestinationId');
                if (! app(PaymentDestinationLookup::class)->available((int) $method,
                    $destination === null ? null : (int) $destination)) {
                    $validator->errors()->add('paymentDestinationId',
                        __('validation.exists', ['attribute' => 'paymentDestinationId']));
                }
            },
        ];
    }

    public function idempotencyHash(): string
    {
        // after() has already validated the header before the controller executes.
        return hash('sha256', (string) $this->header('Idempotency-Key'));
    }
}
