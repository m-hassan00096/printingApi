<?php
namespace Modules\Production\Services;

use Illuminate\Support\Facades\DB;
use Modules\Production\Contracts\ProductionOrderGateway;
use Modules\Production\Enums\ProductionEventType;
use Modules\Production\Exceptions\ProductionException;
use Modules\Production\Models\ProductionItemEvent;
use Modules\Settings\Contracts\PrintingLocationLookup;

class ProductionWorkflowService
{
    public function __construct(
        private readonly ProductionOrderGateway $orders,
        private readonly PrintingLocationLookup $locations,
        private readonly ProductionIdempotencyService $keys,
    ) {}

    public function run(array $itemIds, string $action, int $actorId, string $actorName, string $keyHash, ?int $printingLocationId = null): void
    {
        // An idempotent retry must not process the same items twice, even if its previous
        // state is no longer the source stage.
        sort($itemIds, SORT_NUMERIC);
        DB::transaction(function () use ($itemIds, $action, $actorId, $actorName, $keyHash, $printingLocationId): void {
            $claim = $this->keys->claim($actorId, $action, $keyHash, [
                'orderItemIds' => $itemIds, 'printingLocationId' => $printingLocationId,
            ]);
            if ($claim->completed) {
                return;
            }
            $selectedLocation = null;
            if ($printingLocationId !== null) {
                $selectedLocation = $this->locations->lockActive($printingLocationId);
                if ($selectedLocation === null) {
                    throw new ProductionException('invalid_location');
                }
            }
            $events = $this->orders->transition($itemIds, $action, $actorId, $printingLocationId);
            foreach ($events as $event) {
                $type = ProductionEventType::from(match ($event['event']) {
                    'design_started' => 1,
                    'design_completed' => 2,
                    'sent_to_printing' => 3,
                    'printing_location_changed' => 4,
                    'printing_completed' => 5,
                    'undone' => 6,
                });
                $oldId = $event['oldLocationId'];
                $newId = $event['newLocationId'];
                ProductionItemEvent::query()->create([
                    'order_item_id' => $event['orderItemId'],
                    'actor_id' => $actorId, 'actor_name_snapshot' => $actorName,
                    'event_type' => $type->value, 'from_stage' => $event['fromStage'],
                    'to_stage' => $event['toStage'],
                    'old_printing_location_id' => $oldId,
                    'new_printing_location_id' => $newId,
                    'old_printing_location_name' => $oldId === null ? null : $this->locations->name($oldId),
                    'new_printing_location_name' => $newId === null ? null
                        : ($newId === $printingLocationId ? $selectedLocation : $this->locations->name($newId)),
                    'created_at' => now(),
                ]);
            }
            $this->keys->finish($claim);
        }, 3);
    }
}
