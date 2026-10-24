<?php
namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderItemFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'extension' => $this->extension,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            // Physical public storage URL, not an API endpoint or signed link.
            // Legacy private files must be migrated to the public disk first.
            'url' => $this->disk === 'public'
                ? Storage::disk('public')->url($this->path)
                : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
