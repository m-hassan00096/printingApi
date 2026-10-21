<?php

namespace Modules\Identity\Services\Selects;

use App\Exceptions\Select\InvalidSelectRequestException;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Models\Role;

class RoleSelectService
{
    /**
     * All assignable roles: only active roles of the web guard.
     * Prefer a translated alias, falling back to the technical name.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getAllRoles(?string $filter = null): array
    {
        if ($filter !== null && $filter !== 'active') {
            throw new InvalidSelectRequestException();
        }

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale', 'ar');

        return Role::query()
            ->where('guard_name', 'web')
            ->where('status', RoleStatus::ACTIVE->value)
            ->with('translations')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static function (Role $role) use ($locale, $fallback): array {
                $translations = $role->translations;

                $alias = $translations->firstWhere('locale', $locale)?->alias
                    ?? $translations->firstWhere('locale', $fallback)?->alias
                    ?? $translations->firstWhere('locale', 'ar')?->alias;

                return [
                    'value' => $role->id,
                    'label' => $alias ?: $role->name,
                ];
            })
            ->all();
    }
}
