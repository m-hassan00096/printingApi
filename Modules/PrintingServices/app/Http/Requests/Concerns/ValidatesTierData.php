<?php

namespace Modules\PrintingServices\Http\Requests\Concerns;

use Illuminate\Validation\Validator;
use Modules\PrintingServices\Support\Money;

trait ValidatesTierData
{
    protected function checkTierFields(Validator $validator, array $tier, int $index, bool $requireAll): void
    {
        $prefix = 'priceTiers.'.$index.'.';

        foreach (['customerTypeId', 'minQuantity', 'maxQuantity'] as $field) {
            if ($requireAll && ! array_key_exists($field, $tier)) {
                $validator->errors()->add($prefix.$field, 'This field is required for a full tier update.');
            }
        }

        if (! array_key_exists('price', $tier) || ! Money::valid($tier['price'], false)) {
            $validator->errors()->add($prefix.'price', 'Tier price must be strictly positive EGP with at most two decimals.');
        }

        foreach (['customerTypeId', 'minQuantity', 'maxQuantity'] as $field) {
            if (! array_key_exists($field, $tier) || $tier[$field] === null) {
                continue;
            }
            $value = $tier[$field];
            if (! self::validPositiveInteger($value)) {
                $validator->errors()->add($prefix.$field, 'Must be a positive integer within supported numeric storage.');
            }
        }

        $min = $tier['minQuantity'] ?? null;
        $max = $tier['maxQuantity'] ?? null;
        if (self::validPositiveInteger($min) && self::validPositiveInteger($max)
            && (int) $min > (int) $max) {
            $validator->errors()->add($prefix.'maxQuantity', 'End quantity must be greater than or equal to start quantity.');
        }
    }

    protected static function validPositiveInteger(mixed $value): bool
    {
        if (! is_int($value) && ! is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]) !== false;
    }
}

