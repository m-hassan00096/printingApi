<?php
namespace Modules\Customers\Support;

final class CustomerPhone
{
    public static function normalize(string $phone): string
    {
        // Preserve internal whitespace, plus signs, punctuation and leading zeroes.
        return trim($phone);
    }

    public static function hash(string $phone): string
    {
        // Binary-exact uniqueness regardless of MySQL's default text collation.
        return hash('sha256', self::normalize($phone));
    }
}
