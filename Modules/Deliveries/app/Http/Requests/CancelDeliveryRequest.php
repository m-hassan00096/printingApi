<?php
namespace Modules\Deliveries\Http\Requests;

class CancelDeliveryRequest extends DeliveryWriteRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) $this->merge(['reason' => trim($this->input('reason'))]);
    }

    protected function acceptedKeys(): array { return ['reason', 'fundsReturnConfirmed']; }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:3000'],
            'fundsReturnConfirmed' => ['sometimes', 'boolean']];
    }
}
