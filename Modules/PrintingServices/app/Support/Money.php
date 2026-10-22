<?php

namespace Modules\PrintingServices\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    // DECIMAL(30,2) allows 28 whole-number digits.
    public static function valid(mixed $value, bool $allowZero): bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return false;
        }

        $value = (string) $value;
        if (! preg_match('/\A(?:0|[1-9][0-9]{0,27})(?:\.[0-9]{1,2})?\z/D', $value)) {
            return false;
        }

        return $allowZero || (bool) preg_match('/[1-9]/', $value);
    }

    public static function normalize(string|int|float $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }

    // Multiply cents by an integer with decimal string arithmetic; never use floats.
    public static function total(string $unitPrice, int $quantity): string
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => __('printing_services.messages.quantity_positive')]);
        }

        $cents = str_replace('.', '', self::normalize($unitPrice));
        $a = strrev(ltrim($cents, '0') ?: '0');
        $b = strrev((string) $quantity);
        $digits = array_fill(0, strlen($a) + strlen($b) + 1, 0);

        for ($i = 0; $i < strlen($a); $i++) {
            for ($j = 0; $j < strlen($b); $j++) {
                $digits[$i + $j] += (int) $a[$i] * (int) $b[$j];
            }
        }

        for ($i = 0; $i < count($digits) - 1; $i++) {
            $carry = intdiv($digits[$i], 10);
            $digits[$i] %= 10;
            $digits[$i + 1] += $carry;
        }

        $wholeCents = ltrim(implode('', array_reverse($digits)), '0') ?: '0';
        if (strlen($wholeCents) > 30) {
            throw ValidationException::withMessages(['quantity' => __('printing_services.messages.total_overflow')]);
        }

        $wholeCents = str_pad($wholeCents, 3, '0', STR_PAD_LEFT);

        return substr($wholeCents, 0, -2).'.'.substr($wholeCents, -2);
    }
}

