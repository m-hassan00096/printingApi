<?php

namespace Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bankName' => $this->bank_name,
            'accountNumber' => $this->account_number,
            'isDefault' => $this->is_default->value,
            'status' => $this->status->value,
        ];
    }
}
