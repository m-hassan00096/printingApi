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
                $validator->errors()->add($prefix.$field, __('printing_services.messages.tier_full_update_required'));
            }
        }

        if (! array_key_exists('price', $tier) || ! Money::valid($tier['price'], false)) {
            $validator->errors()->add($prefix.'price', __('printing_services.messages.tier_price_invalid'));
        }

        foreach (['customerTypeId', 'minQuantity', 'maxQuantity'] as $field) {
            if (! array_key_exists($field, $tier) || $tier[$field] === null) {
                continue;
            }
            $value = $tier[$field];
            if (! self::validPositiveInteger($value)) {
                $validator->errors()->add($prefix.$field, __('printing_services.messages.positive_integer_required'));
            }
        }

        $min = $tier['minQuantity'] ?? null;
        $max = $tier['maxQuantity'] ?? null;
        if (self::validPositiveInteger($min) && self::validPositiveInteger($max)
            && (int) $min > (int) $max) {
            $validator->errors()->add($prefix.'maxQuantity', __('printing_services.messages.max_quantity_before_min'));
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

