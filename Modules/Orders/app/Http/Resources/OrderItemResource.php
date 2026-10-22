<?php
namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Support\OrderMoney;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serviceId' => $this->printing_service_id,
            'serviceName' => $this->service_name_snapshot,
            'customerTypeId' => $this->customer_type_id_snapshot,
            'customerTypeName' => $this->customer_type_name_snapshot,
            'quantity' => $this->quantity,
            'specifications' => $this->specifications,
            'agreedTotal' => (string) $this->agreed_total,
            'unitPrice' => OrderMoney::unitPrice((string) $this->agreed_total, (int) $this->quantity),
            'suggestedTotal' => $this->suggested_total === null ? null : (string) $this->suggested_total,
            'priceSource' => $this->price_source,
            'wasManuallyPriced' => (bool) $this->was_manually_priced,
            'productionStage' => $this->production_stage->value,
            'designerId' => $this->designer_id,
            'printingLocationId' => $this->printing_location_id,
            'files' => $this->whenLoaded('files', fn (): array => OrderItemFileResource::collection($this->files)->resolve($request)),
        ];
    }
}
