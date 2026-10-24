<?php
namespace Modules\Orders\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesIdempotencyHeader
{
    /** @return array<callable> */
    protected function idempotencyValidationCallbacks(bool $required): array
    {
        return [
            function (Validator $validator) use ($required): void {
                if (! $required) {
                    return;
                }
                $key = $this->header('Idempotency-Key');
                if (! is_string($key) || trim($key) === '' || strlen(trim($key)) > 128) {
                    $validator->errors()->add('Idempotency-Key',
                        __('orders.messages.idempotency_required'));
                }
            },
        ];
    }

    public function idempotencyKey(): string
    {
        // The required operation's FormRequest has already validated the header.
        return trim((string) $this->header('Idempotency-Key'));
    }
}
