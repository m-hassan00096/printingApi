<?php
namespace Modules\Orders\Services;

use Illuminate\Validation\ValidationException;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Orders\Support\OrderMoney;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Services\PriceResolver;

class OrderPricingService
{
    public function __construct(private readonly PriceResolver $resolver) {}

    /** Call inside a transaction before writing references. */
    public function activeCustomer(int $id): Customer
    {
        $customer = Customer::query()->whereKey($id)->lockForUpdate()->first();

        if ($customer === null || $customer->status !== CustomerStatus::ACTIVE) {
            throw ValidationException::withMessages(['customerId' => __('orders.messages.customer_inactive')]);
        }

        if ($customer->customerType === null) {
            throw ValidationException::withMessages(['customerId' => __('orders.messages.customer_inactive')]);
        }

        return $customer;
    }

    public function applyCustomer(Order $order, Customer $customer): void
    {
        $order->customer_id = $customer->getKey();
        $order->customer_name_snapshot = $customer->name;
        $order->customer_phone_snapshot = $customer->phone;
        $order->customer_type_id_snapshot = $customer->customer_type_id;
        $order->customer_type_name_snapshot = $customer->customerType->name;
    }

    /** Lock the service row before a new order-item reference is persisted. */
    public function activeService(int $id): PrintingService
    {
        $service = PrintingService::query()->whereKey($id)->lockForUpdate()->first();
        if ($service === null || $service->status !== PrintingServiceStatus::ACTIVE) {
            throw ValidationException::withMessages(['serviceId' => __('orders.messages.service_unavailable')]);
        }
        return $service;
    }

    public function applyItem(OrderItem $item, Order $order, PrintingService $service, array $data): void
    {
        $quantity = (int) $data['quantity'];
        $agreedTotal = OrderMoney::format(OrderMoney::cents((string) $data['agreedTotal']));
        $preview = $this->resolver->resolve($service, (int) $order->customer_type_id_snapshot, $quantity);
        $suggested = $preview['suggestedTotal'];

        $item->printing_service_id = $service->id;
        $item->service_name_snapshot = $service->name;
        $item->customer_type_id_snapshot = $order->customer_type_id_snapshot;
        $item->customer_type_name_snapshot = $order->customer_type_name_snapshot;
        $item->quantity = $quantity;
        $item->specifications = $data['specifications'] ?? null;
        $item->agreed_total = $agreedTotal;
        $item->suggested_total = $suggested;
        $item->price_source = $preview['source'];
        $item->was_manually_priced = $suggested === null || $suggested !== $agreedTotal;
    }

    public function refreshSuggestionsForNewCustomer(Order $order): void
    {
        foreach ($order->items()->orderBy('printing_service_id')->get() as $item) {
            $service = $this->activeService((int) $item->printing_service_id);

            // A Draft's final edited price remains authoritative; only suggested
            // pricing and type snapshots are updated after customer reassignment.
            $preview = $this->resolver->resolve(
                $service, (int) $order->customer_type_id_snapshot, (int) $item->quantity,
            );
            $item->customer_type_id_snapshot = $order->customer_type_id_snapshot;
            $item->customer_type_name_snapshot = $order->customer_type_name_snapshot;
            $item->suggested_total = $preview['suggestedTotal'];
            $item->price_source = $preview['source'];
            $item->was_manually_priced = $preview['suggestedTotal'] === null
                || $preview['suggestedTotal'] !== (string) $item->agreed_total;
            $item->save();
        }
    }

    public function recomputeTotal(Order $order): void
    {
        $total = OrderMoney::sumCents($order->items()->pluck('agreed_total'));
        $order->total_amount = OrderMoney::format($total);
        $order->save();
    }
}
