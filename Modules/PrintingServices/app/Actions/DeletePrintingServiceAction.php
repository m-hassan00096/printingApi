<?php

namespace Modules\PrintingServices\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Modules\PrintingServices\Exceptions\ServiceInUseException;
use Modules\PrintingServices\Models\PrintingService;

class DeletePrintingServiceAction
{
    public function execute(PrintingService $service): void
    {
        DB::transaction(function () use ($service): void {
            $locked = PrintingService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();

            // Orders does not exist yet. Once integrated its persisted order_items
            // must be protected regardless of soft-deleted/cancelled/draft state.
            if (Schema::hasTable('order_items')) {
                if (! Schema::hasColumn('order_items', 'printing_service_id')) {
                    throw new LogicException('Orders integration must supply printing_service_id before service deletion can be enabled.');
                }
                if (DB::table('order_items')->where('printing_service_id', $locked->id)->exists()) {
                    throw new ServiceInUseException();
                }
            }

            foreach ($locked->priceTiers()->get() as $tier) {
                $tier->delete();
            }

            $locked->live_name_hash = null;
            $locked->save();
            $locked->delete();
        }, 3);
    }
}

