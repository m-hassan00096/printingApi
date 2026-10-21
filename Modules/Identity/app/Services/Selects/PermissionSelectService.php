<?php

namespace Modules\Identity\Services\Selects;

use App\Exceptions\Select\InvalidSelectRequestException;
use Modules\Identity\Models\Permission;

class PermissionSelectService
{
    /**
     * Permission IDs are sent by the Role form. Technical names remain display/fallback labels.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getAllPermissions(?string $filter = null): array
    {
        if ($filter !== null && $filter !== 'all') {
            throw new InvalidSelectRequestException();
        }

        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale', 'ar');

        $query = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name');

        // Supports installations with/without permission translation tables.
        if (method_exists(Permission::class, 'translations')) {
            $query->with('translations');
        }

        return $query->get()
            ->map(static function (Permission $permission) use ($locale, $fallback): array {
                $alias = null;

                if ($permission->relationLoaded('translations')) {
                    $translations = $permission->getRelation('translations');
                    $alias = $translations->firstWhere('locale', $locale)?->alias
                        ?? $translations->firstWhere('locale', $fallback)?->alias
                        ?? $translations->firstWhere('locale', 'ar')?->alias;
                }

                // Some Identity implementations store a single alias on permissions.
                $alias ??= $permission->getAttribute('alias');

                return [
                    'value' => $permission->id,
                    'label' => $alias ?: $permission->name,
                ];
            })
            ->all();
    }
}
