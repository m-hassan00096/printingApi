<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoggedInUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->roles->first();

        $translation = $role?->translations
            ->firstWhere('locale', app()->getLocale())
            ?? $role?->translations
                ->firstWhere('locale', config('app.fallback_locale', 'ar'));

        return [
            'name' => $this->name,
            'username' => $this->username,

            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),

            'role' => $role?->name,
            'roleAlias' => $translation?->alias ?? $role?->name,

            'permissions' => $this->getAllPermissions()
                ->pluck('name')
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
