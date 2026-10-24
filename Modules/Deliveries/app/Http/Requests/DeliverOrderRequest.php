<?php
namespace Modules\Deliveries\Http\Requests;

use Illuminate\Validation\Validator;

class DeliverOrderRequest extends DeliveryWriteRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('receiverName')))
            $this->merge(['receiverName' => trim($this->input('receiverName')) ?: null]);
    }

    protected function acceptedKeys(): array
    {
        return ['receiverName', 'collectedAmount', 'paymentMethod', 'paymentDestinationId', 'confirmCreditBalance'];
    }

    public function rules(): array
    {
        return [
            'receiverName' => ['sometimes', 'nullable', 'string', 'max:150'],
            'collectedAmount' => ['sometimes', 'required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D'],
            'paymentMethod' => ['sometimes', 'nullable', 'integer', 'in:0,1,2'],
            'paymentDestinationId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'confirmCreditBalance' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $v): void {
            if ($v->errors()->has('collectedAmount') || $v->errors()->has('paymentMethod')
                || $v->errors()->has('paymentDestinationId')) return;
            $amount = $this->input('collectedAmount', '0.00');
            $paid = (float) $amount > 0;
            $method = $this->input('paymentMethod');
            $destination = $this->input('paymentDestinationId');
            if ($paid && $method === null) $v->errors()->add('paymentMethod', __('validation.required', ['attribute' => 'paymentMethod']));
            if (! $paid && $method !== null) $v->errors()->add('paymentMethod', __('validation.prohibited', ['attribute' => 'paymentMethod']));
            if ($paid && in_array((string) $method, ['1','2'], true) && $destination === null)
                $v->errors()->add('paymentDestinationId', __('validation.required', ['attribute' => 'paymentDestinationId']));
            if ((! $paid || (string) $method === '0') && $destination !== null)
                $v->errors()->add('paymentDestinationId', __('validation.prohibited', ['attribute' => 'paymentDestinationId']));
        }];
    }
}
