<?php

namespace Modules\Settings\Services\Selects;

use App\Exceptions\Select\InvalidSelectRequestException;
use Illuminate\Database\Eloquent\Builder;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;

class ParameterValueSelectService
{
    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function getPrintingLocations(?string $filter = null): array
    {
        return $this->getActiveValues('printing_locations', $filter);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function getCustomerTypes(?string $filter = null): array
    {
        return $this->getActiveValues('customer_types', $filter);
    }

    /**
     * The technical parameter-group names are controlled by the backend.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function getActiveValues(string $groupName, ?string $filter): array
    {
        // Settings selects only contain assignable, active options.
        // Match Identity select providers by supporting an optional "active" filter.
        if ($filter !== null && $filter !== 'active') {
            throw new InvalidSelectRequestException();
        }

        return ParameterValue::query()
            ->whereHas('parameter', static function (Builder $query) use ($groupName): void {
                $query->where('name', $groupName);
            })
            ->where('status', ParameterValueStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(static fn (ParameterValue $value): array => [
                'value' => $value->id,
                'label' => $value->name,
            ])
            ->all();
    }
}
