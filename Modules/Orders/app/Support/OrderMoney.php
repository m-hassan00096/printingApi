<?php
namespace Modules\Orders\Support;

use Illuminate\Validation\ValidationException;

final class OrderMoney
{
    private const MAX_CENTS = 999999999999;

    public static function valid(mixed $value): bool
    {
        return (is_string($value) || is_int($value))
            && (bool) preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', (string) $value);
    }

    public static function cents(string|int $value): int
    {
        $text = (string) $value;
        if (! self::valid($text)) {
            throw ValidationException::withMessages([
                'agreedTotal' => __('orders.messages.total_overflow'),
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function sumCents(iterable $amounts): int
    {
        $sum = 0;

        foreach ($amounts as $amount) {
            $sum += self::cents((string) $amount);
            if ($sum > self::MAX_CENTS) {
                throw ValidationException::withMessages([
                    'items' => __('orders.messages.total_overflow'),
                ]);
            }
        }

        return $sum;
    }

    public static function unitPrice(string $total, int $quantity): string
    {
        if ($quantity < 1) {
            return '0.0000';
        }

        $cents = self::cents($total);
        $unit = intdiv($cents * 100 + intdiv($quantity, 2), $quantity);

        return intdiv($unit, 10000).'.'.str_pad((string) ($unit % 10000), 4, '0', STR_PAD_LEFT);
    }
}
