<?php
namespace Modules\Settings\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Settings\Contracts\PrintingLocationLookup;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;

class PrintingLocationResolver implements PrintingLocationLookup
{
    /** @var array<int, string|null> */
    private array $nameCache = [];

    private function belongingToGroup(): Builder
    {
        return ParameterValue::withTrashed()
            ->whereHas('parameter', fn (Builder $q) => $q->where('name', 'printing_locations'));
    }

    private function active(): Builder
    {
        return ParameterValue::query()
            ->whereHas('parameter', fn (Builder $q) => $q->where('name', 'printing_locations'))
            ->where('status', ParameterValueStatus::ACTIVE->value);
    }

    public function belongsToPrintingLocations(int $id): bool
    {
        return $this->belongingToGroup()->whereKey($id)->exists();
    }

    public function available(int $id): bool
    {
        return $this->active()->whereKey($id)->exists();
    }

    public function lockActive(int $id): ?string
    {
        $name = $this->active()->whereKey($id)->lockForUpdate()->value('name');
        if ($name !== null) {
            $this->nameCache[$id] = $name;
        }

        return $name;
    }

    public function name(int $id): ?string
    {
        return $this->names([$id])[$id] ?? null;
    }

    public function names(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $missing = array_values(array_diff($ids, array_keys($this->nameCache)));
        if ($missing !== []) {
            $rows = $this->belongingToGroup()->whereIn('id', $missing)
                ->pluck('name', 'id')->all();
            foreach ($missing as $id) {
                $this->nameCache[$id] = $rows[$id] ?? null;
            }
        }

        $names = [];
        foreach ($ids as $id) {
            $names[$id] = $this->nameCache[$id] ?? null;
        }

        return $names;
    }
}
