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
            'basePrice' => $this->base_price,
            'status' => $this->status->value,
            'priceTiers' => $this->whenLoaded(
                'priceTiers',
                fn () => PrintingServiceTierResource::collection($this->priceTiers)->resolve($request),
            ),
        ];
    }
}

