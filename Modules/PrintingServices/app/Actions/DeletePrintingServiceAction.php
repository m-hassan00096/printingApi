<?php

namespace Modules\PrintingServices\Actions;

use Illuminate\Support\Facades\DB;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Services\PrintingServiceUsageRegistry;

class DeletePrintingServiceAction
{
    public function __construct(
        private readonly PrintingServiceUsageRegistry $usageRegistry,
    ) {}

    public function execute(PrintingService $service): void
    {
        DB::transaction(function () use ($service): void {
            $locked = PrintingService::query()
                ->whereKey($service->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Orders will register a guard from its own provider when it exists.
            // It checks all persisted items (including cancelled/soft-deleted
            // history) without exposing its tables to PrintingServices.
            $this->usageRegistry->assertCanDelete($locked);

            foreach ($locked->priceTiers()->get() as $tier) {
                $tier->delete();
            }

            $locked->live_name_hash = null;
            $locked->save();
            $locked->delete();
        }, 3);
    }
}

