<?php
namespace Modules\Finance\Support;

use InvalidArgumentException;

final class Money
{
    /** Integer cents avoid floating-point rounding, including ledger SUMs. */
    public static function cents(string $amount): int
    {
        if (! preg_match('/\A(-?)(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?\z/D', $amount, $m)) {
            throw new InvalidArgumentException('Invalid decimal money');
        }
        $value = ((int) $m[2] * 100) + (int) str_pad($m[3] ?? '', 2, '0');
        return ($m[1] === '-' ? -1 : 1) * $value;
    }

    public static function format(int $cents): string
    {
        $negative = $cents < 0 ? '-' : '';
        $positive = abs($cents);
        return $negative.intdiv($positive, 100).'.'.str_pad((string) ($positive % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function positive(string $amount): int
    {
        $cents = self::cents($amount);
        if ($cents <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }
        return $cents;
    }
}
