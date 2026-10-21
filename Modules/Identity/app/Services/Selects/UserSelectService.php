<?php

namespace Modules\Identity\Services\Selects;

use App\Exceptions\Select\InvalidSelectRequestException;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;

class UserSelectService
{
    /**
     * List users that can be selected (active, not soft-deleted).
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getAllUsers(?string $filter = null): array
    {
        if ($filter !== null && $filter !== 'active') {
            throw new InvalidSelectRequestException();
        }

        return User::query()
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name,
            ])
            ->all();
    }
}
