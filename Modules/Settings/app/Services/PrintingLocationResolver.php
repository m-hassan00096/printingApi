<?php
namespace Modules\Settings\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Settings\Contracts\PrintingLocationLookup;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;

class PrintingLocationResolver implements PrintingLocationLookup
{
    private function scope(): Builder
    {
        return ParameterValue::query()
            ->whereHas('parameter', fn (Builder $q) => $q->where('name', 'printing_locations'))
            ->where('status', ParameterValueStatus::ACTIVE->value);
    }

    public function available(int $id): bool
    {
        return $this->scope()->whereKey($id)->exists();
    }

    public function lockActive(int $id): ?string
    {
        return $this->scope()->whereKey($id)->lockForUpdate()->value('name');
    }

    public function name(int $id): ?string
    {
        return ParameterValue::withTrashed()->whereKey($id)
            ->whereHas('parameter', fn (Builder $q) => $q->where('name', 'printing_locations'))
            ->value('name');
    }
}
