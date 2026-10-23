<?php
namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class OrderItemFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Signed URLs never expose the private storage path. Issuing a URL
        // requires the independent permission for actually viewing file bytes.
        $canView = $request->user()?->can('orders.files.view') === true;
        $expiresAt = $canView ? now()->addMinutes(15) : null;

        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'extension' => $this->extension,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'url' => $canView ? URL::temporarySignedRoute(
                'orders.files.preview', $expiresAt, ['file' => $this->id],
            ) : null,
            'expiresAt' => $expiresAt?->toIso8601String(),
            'uploadedBy' => $this->uploaded_by,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
