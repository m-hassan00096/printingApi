<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Enums\RoleStatus;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translations = $this->translations;
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale', 'ar');
        $nameAr = $translations->firstWhere('locale', 'ar')?->alias;
        $label = $translations->firstWhere('locale', $locale)?->alias
            ?? $translations->firstWhere('locale', $fallback)?->alias
            ?? $nameAr
            ?? $this->name;

        $status = $this->status instanceof RoleStatus
            ? $this->status
            : RoleStatus::from((int) $this->status);

        return [
            'id' => $this->id,
            'name' => $label,
            'nameAr' => $nameAr,
            'key' => $this->name,
            'status' => $status->value,
            'statusLabel' => __('role_management.status.'.strtolower($status->name)),
            'usersCount' => (int) ($this->users_count ?? 0),
            'permissionIds' => $this->whenLoaded(
                'permissions',
                fn (): array => $this->permissions->pluck('id')->sort()->values()->all()
            ),
        ];
    }
}
