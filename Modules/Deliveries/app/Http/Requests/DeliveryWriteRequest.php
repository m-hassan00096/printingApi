<?php
namespace Modules\Deliveries\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class DeliveryWriteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    abstract protected function acceptedKeys(): array;

    public function after(): array
    {
        return [function (Validator $v): void {
            $key = $this->header('Idempotency-Key');
            if (! is_string($key) || trim($key) === '' || strlen($key) > 160)
                $v->errors()->add('Idempotency-Key', __('deliveries.messages.idempotency_required'));
            if (array_diff(array_keys($this->all()), $this->acceptedKeys()) !== [])
                $v->errors()->add('body', __('deliveries.messages.unexpected_fields'));
        }];
    }

    public function idempotencyHash(): string
    {
        return hash('sha256', (string) $this->header('Idempotency-Key'));
    }
}
