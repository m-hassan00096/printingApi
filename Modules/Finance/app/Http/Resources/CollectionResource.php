<?php
namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'receiptNumber' => $this->receipt_number,
            'customerId' => $this->customer_id, 'customerName' => $this->customer_name_snapshot,
            'customerPhone' => $this->customer_phone_snapshot,
            'orderId' => $this->order_id,
            'orderNumber' => $this->order_id === null ? null : $this->order?->order_number,
            'correctedFromId' => $this->corrected_from_id,
            'correctedToId' => $this->corrected_to_id,
            'amount' => (string) $this->amount,
            'paymentMethod' => $this->payment_method->value, 'paymentMethodLabel' => $this->payment_method->label(),
            'paymentDestinationId' => $this->bank_account_id ?? $this->e_wallet_id,
            'destinationLabel' => $this->destination_snapshot,
            'source' => $this->source->value, 'sourceLabel' => $this->source->label(),
            'status' => $this->status->value, 'statusLabel' => $this->status->label(),
            'collectedOn' => $this->collected_on->format('Y-m-d'),
            'note' => $this->note, 'createdBy' => $this->created_by,
            'createdAt' => $this->created_at?->toIso8601String(),
            'reversedAt' => $this->reversed_at?->toIso8601String(),
            'reversalReason' => $this->reversal_reason,
        ];
    }
}
