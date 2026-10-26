<?php
namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Enums\OrderItemStage;
use Modules\Orders\Enums\OrderSource;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Support\OrderMoney;

class DraftOrderService
{
    public function __construct(
        private readonly OrderPricingService $pricing,
        private readonly OrderActivityWriter $activities,
        private readonly OrderFileArchiveService $fileArchives,
    ) {}

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $customer = $this->pricing->activeCustomer((int) $data['customerId']);
            $order = new Order();
            $this->pricing->applyCustomer($order, $customer);
            $order->source = OrderSource::from((int) $data['source']);
            $order->status = OrderStatus::DRAFT;
            $order->production_status = null;
            $order->due_date = $data['dueDate'] ?? null;
            $order->notes = $data['notes'] ?? null;
            $order->total_amount = '0.00';
            $order->planned_deposit_amount = $data['plannedDepositAmount'] ?? null;
            $order->planned_payment_method = $data['plannedPaymentMethod'] ?? null;
            $order->planned_payment_destination_id = $data['plannedPaymentDestinationId'] ?? null;
            $order->save();

            foreach ($data['items'] as $line) {
                $this->persistNewItem($order, $line);
            }

            $this->pricing->recomputeTotal($order);
            $this->activities->record($order, 'draft_created', ['itemCount' => count($data['items'])]);

            return $this->refresh($order);
        }, 3);
    }

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $locked = $this->lockedDraft($order);
            $changes = [];

            if (array_key_exists('customerId', $data)
                && (int) $data['customerId'] !== (int) $locked->customer_id) {
                $oldId = $locked->customer_id;
                $customer = $this->pricing->activeCustomer((int) $data['customerId']);
                $this->pricing->applyCustomer($locked, $customer);
                $locked->save();
                $this->pricing->refreshCustomerTypeSnapshots($locked);
                $changes['customerId'] = ['from' => $oldId, 'to' => $customer->id];
            }

            if (array_key_exists('source', $data)) {
                $locked->source = OrderSource::from((int) $data['source']);
            }

            if (array_key_exists('dueDate', $data)) {
                $old = $locked->due_date?->format('Y-m-d');
                $locked->due_date = $data['dueDate'];
                if ($old !== $data['dueDate']) {
                    $changes['dueDate'] = ['from' => $old, 'to' => $data['dueDate']];
                }
            }

            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            if (array_key_exists('plannedDepositAmount', $data)) {
                $locked->planned_deposit_amount = $data['plannedDepositAmount'];
                $locked->planned_payment_method = $data['plannedPaymentMethod'] ?? null;
                $locked->planned_payment_destination_id = $data['plannedPaymentDestinationId'] ?? null;
                $changes['plannedPayment'] = 'updated';
            }

            $locked->save();

            if ($changes !== []) {
                $this->activities->record($locked, 'draft_updated', $changes);
            }

            return $this->refresh($locked);
        }, 3);
    }

    public function addItem(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $locked = $this->lockedDraft($order);
            $item = $this->persistNewItem($locked, $data);
            $this->pricing->recomputeTotal($locked);
            $this->activities->record($locked, 'item_added', ['itemId' => $item->id]);
            return $this->refresh($locked);
        }, 3);
    }

    public function updateItem(Order $order, int $itemId, array $data): Order
    {
        return DB::transaction(function () use ($order, $itemId, $data): Order {
            $locked = $this->lockedDraft($order);
            $item = $locked->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $oldTotal = (string) $item->agreed_total;

            if (array_key_exists('serviceId', $data)
                || array_key_exists('quantity', $data)) {
                $service = $this->pricing->activeService(
                    (int) ($data['serviceId'] ?? $item->printing_service_id),
                );

                $updated = [
                    'quantity' => $data['quantity'] ?? $item->quantity,
                    'agreedTotal' => $data['agreedTotal'] ?? $item->agreed_total,
                    'specifications' => $data['specifications'] ?? $item->specifications,
                ];

                if ($service->id !== (int) $item->printing_service_id) {
                    // Preserve every historic service reference/snapshot. A
                    // changed service is a new order item, never an in-place
                    // reassignment that would free the old service for deletion.
                    $oldItemId = $item->id;
                    $replacement = new OrderItem();
                    $this->pricing->applyItem($replacement, $locked, $service, $updated);
                    $replacement->production_stage = OrderItemStage::NOT_STARTED;
                    $locked->items()->save($replacement);
                    $item->files()->update(['order_item_id' => $replacement->id]);
                    $item->delete();
                    $item = $replacement;
                    $this->activities->record($locked, 'item_service_replaced', [
                        'previousItemId' => $oldItemId,
                        'newItemId' => $replacement->id,
                    ]);
                } else {
                    $this->pricing->applyItem($item, $locked, $service, $updated);
                }
            } else {
                if (array_key_exists('specifications', $data)) {
                    $item->specifications = $data['specifications'];
                }

                if (array_key_exists('agreedTotal', $data)) {
                    $item->agreed_total = OrderMoney::format(OrderMoney::cents((string) $data['agreedTotal']));
                }
            }

            $item->save();
            $this->pricing->recomputeTotal($locked);

            if ($oldTotal !== (string) $item->agreed_total) {
                $this->activities->record($locked, 'price_changed', [
                    'itemId' => $item->id, 'from' => $oldTotal, 'to' => (string) $item->agreed_total,
                ]);
            } else {
                $this->activities->record($locked, 'item_updated', ['itemId' => $item->id]);
            }

            return $this->refresh($locked);
        }, 3);
    }

    public function removeItem(Order $order, int $itemId): Order
    {
        return $this->fileArchives->transaction(function (array &$moves) use ($order, $itemId): Order {
            $locked = $this->lockedDraft($order);
            if ($locked->items()->count() <= 1) {
                throw new OrdersBusinessException('last_item_required');
            }

            $item = $locked->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();

            // Archive public uploads before hiding their parent item.
            foreach ($item->files()->lockForUpdate()->get() as $file) {
                $this->fileArchives->archive($file, $moves);
            }

            $item->delete();
            $this->pricing->recomputeTotal($locked);
            $this->activities->record($locked, 'item_removed', ['itemId' => $itemId]);

            return $this->refresh($locked);
        });
    }

    public function delete(Order $order): void
    {
        $this->fileArchives->transaction(function (array &$moves) use ($order): void {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::DRAFT) {
                throw new OrdersBusinessException('only_drafts_deletable');
            }

            // Move all public assets to private storage, including assets of
            // items already soft-deleted in a previously saved draft.
            foreach ($locked->items()->withTrashed()->orderBy('id')->get() as $item) {
                foreach ($item->files()->withTrashed()->lockForUpdate()->get() as $file) {
                    $this->fileArchives->archive($file, $moves);
                }
            }

            $this->activities->record($locked, 'draft_deleted');
            $locked->delete();
        });
    }

    public function lockedDraft(Order $order): Order
    {
        $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
        if ($locked->status !== OrderStatus::DRAFT) {
            throw new OrdersBusinessException('only_drafts_editable');
        }
        return $locked;
    }

    private function persistNewItem(Order $order, array $data): OrderItem
    {
        $service = $this->pricing->activeService((int) $data['serviceId']);
        $item = new OrderItem();
        $this->pricing->applyItem($item, $order, $service, $data);
        $item->production_stage = OrderItemStage::NOT_STARTED;
        $order->items()->save($item);

        return $item;
    }

    private function refresh(Order $order): Order
    {
        return $order->fresh()->load(['customer', 'items.files']);
    }
}
