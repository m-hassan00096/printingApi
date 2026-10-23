<?php
namespace Modules\Orders\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class OrderIdempotencyKey
{
    public static function required(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '' || strlen($key) > 128) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => __('orders.messages.idempotency_required'),
            ]);
        }

        return $key;
    }
}
