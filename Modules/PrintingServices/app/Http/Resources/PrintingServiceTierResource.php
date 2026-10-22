<?php

namespace Modules\PrintingServices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintingServiceTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'priceTierId' => $this->id,
            'customerTypeId' => $this->customer_type_id,
            'minQuantity' => $this->min_quantity,
            'maxQuantity' => $this->max_quantity,
            'price' => $this->price,
        ];
    }
}

