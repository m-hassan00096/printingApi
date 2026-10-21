<?php

namespace Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParameterValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parameterId' => $this->parameter_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
        ];
    }
}
