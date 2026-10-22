<?php
namespace Modules\Orders\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;

/** Deduplicates draft creation across request retries and parallel submissions. */
class OrderDraftIdempotencyService
{
    public function __construct(private readonly DraftOrderService $drafts) {}

    public function create(array $data, string $key): Order
    {
        $actorId = (int) Auth::id();
        $fingerprint = hash('sha256', json_encode(
            $this->canonicalize($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        ));

        return DB::transaction(function () use ($actorId, $data, $key, $fingerprint): Order {
            DB::table('order_idempotency_keys')->insertOrIgnore([
                'actor_id' => $actorId,
                'operation' => 'draft_create',
                'idempotency_key' => $key,
                'request_hash' => $fingerprint,
                'order_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = DB::table('order_idempotency_keys')
                ->where('actor_id', $actorId)
                ->where('operation', 'draft_create')
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals($record->request_hash, $fingerprint)) {
                throw new OrdersBusinessException('idempotency_reused');
            }

            if ($record->order_id !== null) {
                // Never recreate a deleted draft on an old retry.
                $order = Order::query()->find((int) $record->order_id);
                if ($order === null) {
                    throw new OrdersBusinessException('idempotency_reused');
                }

                return $order->load(['customer', 'items.files']);
            }

            $order = $this->drafts->create($data);

            DB::table('order_idempotency_keys')->where('id', $record->id)->update([
                'order_id' => $order->id,
                'updated_at' => now(),
            ]);

            return $order;
        }, 3);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
