<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->roles->first();
        $translation = $role?->translations->firstWhere('locale', app()->getLocale())
            ?? $role?->translations->firstWhere('locale', config('app.fallback_locale', 'ar'));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'phone' => $this->phone,
            'address' => $this->address,
            'avatar' => $this->avatar,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'alias' => $translation?->alias ?? $role->name,
            ] : null,
        ];
    }
}
