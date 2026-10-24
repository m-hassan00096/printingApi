<?php
namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eventType' => $this->event_type,
            'actorId' => $this->actor_id,
            'context' => $this->context,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
