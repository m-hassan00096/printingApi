<?php
namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'refundNumber' => $this->refund_number,
            'customerId' => $this->customer_id, 'customerName' => $this->customer_name_snapshot,
            'amount' => (string) $this->amount,
            'paymentMethod' => $this->payment_method->value, 'paymentMethodLabel' => $this->payment_method->label(),
            'paymentDestinationId' => $this->bank_account_id ?? $this->e_wallet_id,
            'destinationLabel' => $this->destination_snapshot, 'reason' => $this->reason,
            'refundedOn' => $this->refunded_on->format('Y-m-d'),
            'createdBy' => $this->created_by, 'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
