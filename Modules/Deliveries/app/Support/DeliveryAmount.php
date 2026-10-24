<?php
namespace Modules\Deliveries\Support;

/** Validated, unsigned request amounts only; real ledger arithmetic remains Finance-owned. */
final class DeliveryAmount
{
    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
