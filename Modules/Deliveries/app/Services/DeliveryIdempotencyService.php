<?php
namespace Modules\Deliveries\Services;

use Illuminate\Support\Facades\DB;
use Modules\Deliveries\Exceptions\DeliveryException;

class DeliveryIdempotencyService
{
    public function claim(int $actorId, string $operation, string $keyHash, array $payload): object
    {
        $this->sort($payload);
        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        DB::table('delivery_idempotency_keys')->insertOrIgnore([
            'actor_id' => $actorId, 'operation' => $operation, 'key_hash' => $keyHash,
            'request_hash' => $fingerprint, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $claim = DB::table('delivery_idempotency_keys')->where('actor_id', $actorId)
            ->where('operation', $operation)->where('key_hash', $keyHash)->lockForUpdate()->firstOrFail();
        if (! hash_equals($claim->request_hash, $fingerprint)) {
            throw new DeliveryException('idempotency_reused');
        }
        return $claim;
    }

    public function finish(object $claim, int $deliveryId): void
    {
        DB::table('delivery_idempotency_keys')->where('id', $claim->id)
            ->update(['result_delivery_id' => $deliveryId, 'updated_at' => now()]);
    }

    private function sort(array &$a): void
    {
        if (! array_is_list($a)) ksort($a);
        foreach ($a as &$v) if (is_array($v)) $this->sort($v);
    }
}
