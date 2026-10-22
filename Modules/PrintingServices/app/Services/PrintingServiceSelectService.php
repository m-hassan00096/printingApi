<?php

namespace Modules\PrintingServices\Services;

use App\Exceptions\Select\InvalidSelectRequestException;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Models\PrintingService;

class PrintingServiceSelectService
{
    public function getActive(?string $filter = null): array
    {
        if ($filter !== null && $filter !== 'active') {
            throw new InvalidSelectRequestException();
        }

        return PrintingService::query()
            ->where('status', PrintingServiceStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (PrintingService $service): array => [
                'value' => $service->id,
                'label' => $service->name,
            ])->all();
    }
}

