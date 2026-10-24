<?php
namespace Modules\Production\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ProductionWriteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $key = $this->header('Idempotency-Key');
            if (! is_string($key) || trim($key) === '' || strlen($key) > 160) {
                $validator->errors()->add('Idempotency-Key', __('production.messages.idempotency_required'));
            }
        }];
    }

    public function idempotencyHash(): string
    {
        return hash('sha256', (string) $this->header('Idempotency-Key'));
    }
}
