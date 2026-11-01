<?php

namespace Modules\PrintingServices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintingServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'pricingMethod' => $this->pricing_method->value,
            'basePrice' => $this->base_price === null ? null : ($this->pricing_method->value === 'quantity' ? bcadd((string) $this->base_price, '0', 2) : (string) $this->base_price),
            'costPrice' => $this->cost_price,
            'status' => $this->status->value,
            'priceTiers' => $this->whenLoaded(
                'priceTiers',
                fn () => PrintingServiceTierResource::collection($this->priceTiers)->resolve($request),
            ),
        ];
    }
}

