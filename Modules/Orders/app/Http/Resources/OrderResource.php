<?php
namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Services\OrderDelayCalculator;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $delay = app(OrderDelayCalculator::class)->calculate($this->resource);

        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'customerId' => $this->customer_id,
            'customerName' => $this->customer?->name ?? $this->customer_name_snapshot,
            'customerPhone' => $this->customer?->phone ?? $this->customer_phone_snapshot,
            'customerTypeId' => $this->customer_type_id_snapshot,
            'customerTypeName' => $this->customer_type_name_snapshot,
            'source' => $this->source->value,
            'sourceLabel' => $this->source->label(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'productionStatus' => $this->production_status?->value,
            'productionStatusLabel' => $this->production_status?->label(),
            'delayStatus' => $delay?->value,
            'delayStatusLabel' => $delay?->label(),
            'dueDate' => $this->due_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'itemsCount' => $this->items_count ?? ($this->relationLoaded('items') ? $this->items->count() : null),
            'totalAmount' => (string) $this->total_amount,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'confirmedAt' => $this->confirmed_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items'))->resolve($request),
        ];
    }
}
