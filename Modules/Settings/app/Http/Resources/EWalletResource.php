<?php

namespace Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'accountHolder' => $this->account_holder,
            'paymentLink' => $this->payment_link,
            'isDefault' => $this->is_default,
            'status' => $this->status->value,
        ];
    }
}
