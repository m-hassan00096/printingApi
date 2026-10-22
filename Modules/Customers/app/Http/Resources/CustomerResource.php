<?php
namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'customerTypeId' => $this->customer_type_id,
            'customerType' => $this->whenLoaded('customerType', function (): ?array {
                if ($this->customerType === null) {
                    return null;
                }

                return [
                    'id' => $this->customerType->id,
                    'name' => $this->customerType->name,
                ];
            }),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
        ];
    }
}
