<?php

namespace Modules\PrintingServices\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Models\PrintingServicePriceTier;
use Modules\PrintingServices\Support\Money;

class PriceResolver
{
    public function __construct(private readonly PriceTierRuleService $rules) {}

    public function resolve(PrintingService $service, ?int $customerTypeId, int $quantity): array
    {
        if ($service->status !== PrintingServiceStatus::ACTIVE) {
            throw ValidationException::withMessages(['serviceId' => 'Service is inactive.']);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        $this->rules->assertCustomerType($customerTypeId);

        $tier = null;
        $source = null;
        if ($customerTypeId !== null) {
            $tier = $this->constrained($service, $customerTypeId, $quantity);
            $source = $tier ? 'customerTypeQuantityTier' : null;

            if ($tier === null) {
                $tier = $this->catchAll($service, $customerTypeId);
                $source = $tier ? 'customerTypeCatchAll' : null;
            }
        }

        if ($tier === null) {
            $tier = $this->constrained($service, null, $quantity);
            $source = $tier ? 'genericQuantityTier' : null;
        }

        if ($tier === null) {
            $tier = $this->catchAll($service, null);
            $source = $tier ? 'genericCatchAll' : null;
        }

        $price = $tier?->price ?? $service->base_price;
        if ($price === null) {
            return [
                'serviceId' => $service->id,
                'quantity' => $quantity,
                'source' => null,
                'suggestedUnitPrice' => null,
                'suggestedTotal' => null,
                'requiresManualTotal' => true,
            ];
        }

        return [
            'serviceId' => $service->id,
            'quantity' => $quantity,
            'source' => $source ?? 'basePrice',
            'suggestedUnitPrice' => Money::normalize($price),
            'suggestedTotal' => Money::total($price, $quantity),
            'requiresManualTotal' => false,
        ];
    }

    private function constrained(PrintingService $service, ?int $typeId, int $quantity): ?PrintingServicePriceTier
    {
        return $service->priceTiers()
            ->where('customer_type_id', $typeId)
            ->where(fn (Builder $query) => $query->whereNull('min_quantity')->orWhere('min_quantity', '<=', $quantity))
            ->where(fn (Builder $query) => $query->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity))
            ->where(fn (Builder $query) => $query->whereNotNull('min_quantity')->orWhereNotNull('max_quantity'))
            ->first();
    }

    private function catchAll(PrintingService $service, ?int $typeId): ?PrintingServicePriceTier
    {
        return $service->priceTiers()
            ->where('customer_type_id', $typeId)
            ->whereNull('min_quantity')
            ->whereNull('max_quantity')
            ->first();
    }
}

