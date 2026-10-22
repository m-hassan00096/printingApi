<?php

namespace Modules\PrintingServices\Services;

use Illuminate\Validation\ValidationException;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\PrintingServices\Support\Money;
use Modules\Settings\Models\ParameterValue;

final class PriceTierRuleService
{
    public function assertPrice(mixed $price): void
    {
        if (! Money::valid($price, false)) {
            throw ValidationException::withMessages([
                'priceTiers' => 'Tier price must be strictly greater than zero and have at most two decimal places.',
            ]);
        }
    }

    public function assertCustomerType(?int $customerTypeId): void
    {
        if ($customerTypeId === null) {
            return;
        }

        $exists = ParameterValue::query()
            ->whereKey($customerTypeId)
            ->where('status', ParameterValueStatus::ACTIVE->value)
            ->whereHas('parameter', fn ($query) => $query->where('name', 'customer_types'))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'customerTypeId' => 'Customer type must be an active Settings customer type.',
            ]);
        }
    }

    /**
     * @param array<int, array{customerTypeId: ?int, minQuantity: ?int, maxQuantity: ?int}> $tiers
     */
    public function assertValidFinalRanges(array $tiers): void
    {
        $buckets = [];
        foreach ($tiers as $tier) {
            $min = $tier['minQuantity'];
            $max = $tier['maxQuantity'];
            if (($min !== null && $min < 1)
                || ($max !== null && $max < 1)
                || ($min !== null && $max !== null && $min > $max)) {
                throw ValidationException::withMessages([
                    'priceTiers' => 'Invalid quantity range.',
                ]);
            }

            $key = $tier['customerTypeId'] === null ? 'generic' : 'type:'.$tier['customerTypeId'];
            $buckets[$key][] = $tier;
        }

        foreach ($buckets as $bucket) {
            $catchAllFound = false;
            $ranges = [];
            foreach ($bucket as $tier) {
                if ($tier['minQuantity'] === null && $tier['maxQuantity'] === null) {
                    if ($catchAllFound) {
                        throw ValidationException::withMessages(['priceTiers' => 'Only one catch-all tier is allowed per customer type.']);
                    }
                    $catchAllFound = true;
                    continue;
                }

                foreach ($ranges as $previous) {
                    $min = $tier['minQuantity'] ?? 1;
                    $prevMin = $previous['minQuantity'] ?? 1;
                    $max = $tier['maxQuantity'];
                    $prevMax = $previous['maxQuantity'];
                    if (($prevMax === null || $min <= $prevMax)
                        && ($max === null || $prevMin <= $max)) {
                        throw ValidationException::withMessages(['priceTiers' => 'Overlapping quantity tiers are not permitted for the same customer type.']);
                    }
                }

                $ranges[] = $tier;
            }
        }
    }
}

