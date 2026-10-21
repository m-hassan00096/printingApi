<?php

namespace Modules\Identity\Services;

use Illuminate\Support\Str;

class RoleNameGenerator
{
    /**
     * A role's immutable technical name is derived once from its Arabic alias.
     * ULID avoids collisions and provides a safe fallback if transliteration is unavailable.
     */
    public function generate(string $arabicName): string
    {
        $slug = Str::slug($arabicName, '-');
        $slug = $slug !== '' ? Str::limit($slug, 170, '') : 'role';

        return $slug.'-'.strtolower((string) Str::ulid());
    }
}
