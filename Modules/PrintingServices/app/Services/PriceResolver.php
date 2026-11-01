<?php

namespace Modules\PrintingServices\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Enums\PricingMethod;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Models\PrintingServicePriceTier;
use Modules\PrintingServices\Support\Money;

class PriceResolver
{
    public function __construct(private readonly PriceTierRuleService $rules, private readonly AreaPriceCalculator $calculator) {}

    public function resolve(PrintingService $service, ?int $customerTypeId, int $quantity, ?string $widthCm = null, ?string $heightCm = null): array
    {
        if ($service->status !== PrintingServiceStatus::ACTIVE) {
            throw ValidationException::withMessages(['serviceId' => __('printing_services.messages.inactive_service')]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => __('printing_services.messages.quantity_positive')]);
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

        // Unclassified customers deliberately use the service base price.
        // Classified customers preserve the existing generic-tier fallback.
        if ($customerTypeId !== null && $tier === null) {
            $tier = $this->constrained($service, null, $quantity);
            $source = $tier ? 'genericQuantityTier' : null;
        }

        if ($customerTypeId !== null && $tier === null) {
            $tier = $this->catchAll($service, null);
            $source = $tier ? 'genericCatchAll' : null;
        }

        // A missing base price remains zero rather than changing manual pricing semantics.
        $rate = (string) ($tier?->price ?? $service->base_price ?? '0.00');
        $method = $service->pricing_method;
        $calculation = $this->calculator->calculate($method, $rate, $quantity, $widthCm, $heightCm);

        return [
            'serviceId' => $service->id,
            'quantity' => $quantity,
            'pricingMethod' => $method->value,
            'widthCm' => $method === PricingMethod::QUANTITY ? null : $widthCm,
            'heightCm' => $method === PricingMethod::QUANTITY ? null : $heightCm,
            'area' => $calculation['area'],
            'suggestedPiecePrice' => $calculation['piecePrice'],
            'source' => $source ?? 'basePrice',
            'suggestedUnitPrice' => $method === PricingMethod::QUANTITY
                ? bcadd($rate, '0', 2)
                : rtrim(rtrim($rate, '0'), '.') ?: '0',
            'suggestedTotal' => $calculation['total'],
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

