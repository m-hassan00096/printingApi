<?php
namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Internal disk/path intentionally never returned to frontend.
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'extension' => $this->extension,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'uploadedBy' => $this->uploaded_by,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
