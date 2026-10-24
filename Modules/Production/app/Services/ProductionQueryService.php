<?php
namespace Modules\Production\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Production\Contracts\ProductionOrderGateway;
use Modules\Production\Enums\ProductionEventType;
use Modules\Production\Models\ProductionItemEvent;
use Modules\Settings\Contracts\PrintingLocationLookup;

class ProductionQueryService
{
    public function __construct(private readonly ProductionOrderGateway $orders, private readonly PrintingLocationLookup $locations) {}

    private function myItems(array $filters, int $actorId): ?Builder
    {
        $mode = $filters['myDesignWork'] ?? null;
        if ($mode === null) return null;
        $type = (int) $mode === 1 ? ProductionEventType::DESIGN_STARTED : ProductionEventType::DESIGN_COMPLETED;

        // SQL subquery is executed by Orders, with bound actor ID and indexed event type.
        return ProductionItemEvent::query()->select('order_item_id')
            ->where('actor_id', $actorId)->where('event_type', $type->value)->distinct();
    }

    public function items(array $filters, int $page, int $perPage, string $sort, int $actorId): array
    {
        $result = $this->orders->listItems($filters, $page, $perPage, $sort, $this->myItems($filters, $actorId));
        $ids = array_column($result['items'], 'id');
        $completers = [];
        if ($ids !== []) {
            $lastIds = ProductionItemEvent::query()->whereIn('order_item_id', $ids)
                ->where('event_type', ProductionEventType::DESIGN_COMPLETED->value)
                ->selectRaw('MAX(id) AS id')->groupBy('order_item_id')->pluck('id');
            $completers = ProductionItemEvent::query()->whereIn('id', $lastIds)
                ->get()->keyBy('order_item_id')->all();
        }
        $locationIds = array_values(array_unique(array_filter(array_column($result['items'], 'printingLocationId'),
            static fn ($id): bool => $id !== null)));
        $locationNames = $this->locations->names($locationIds);
        foreach ($result['items'] as &$item) {
            $id = $item['id'];
            $stage = (int) $item['productionStage'];
            $item['productionStageLabel'] = __('production.stages.'.$stage);
            $item['printingLocationName'] = $item['printingLocationId'] === null
                ? null : ($locationNames[(int) $item['printingLocationId']] ?? null);
            $item['designCompletedById'] = isset($completers[$id]) ? $completers[$id]->actor_id : null;
            $item['designCompletedByName'] = isset($completers[$id]) ? $completers[$id]->actor_name_snapshot : null;
        }
        unset($item);
        return $result;
    }

    public function summary(array $filters, int $actorId): array
    {
        return $this->orders->summary($filters, $this->myItems($filters, $actorId));
    }

    public function history(int $itemId, int $page, int $perPage): array
    {
        abort_unless($this->orders->itemExists($itemId), 404);
        $p = ProductionItemEvent::query()->where('order_item_id', $itemId)
            ->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        return [
            'events' => $p->getCollection()->map(fn (ProductionItemEvent $e): array => [
                'id' => $e->id, 'orderItemId' => $e->order_item_id,
                'eventType' => $e->event_type->value, 'eventTypeLabel' => $e->event_type->label(),
                'actorId' => $e->actor_id, 'actorName' => $e->actor_name_snapshot,
                'fromStage' => $e->from_stage, 'toStage' => $e->to_stage,
                'oldPrintingLocationId' => $e->old_printing_location_id,
                'newPrintingLocationId' => $e->new_printing_location_id,
                'oldPrintingLocationName' => $e->old_printing_location_name,
                'newPrintingLocationName' => $e->new_printing_location_name,
                'createdAt' => $e->created_at?->toIso8601String(),
            ])->all(),
            'pagination' => [
                'currentPage' => $p->currentPage(), 'lastPage' => $p->lastPage(),
                'perPage' => $p->perPage(), 'total' => $p->total(),
            ],
        ];
    }
}
