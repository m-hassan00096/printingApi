<?php
namespace Modules\Production\Services;

use Illuminate\Support\Facades\DB;
use Modules\Production\Exceptions\ProductionException;

class ProductionIdempotencyService
{
    public function claim(int $actorId, string $action, string $keyHash, array $payload): object
    {
        $this->sort($payload);
        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        DB::table('production_idempotency_keys')->insertOrIgnore([
            'actor_id' => $actorId, 'operation' => $action, 'key_hash' => $keyHash,
            'request_hash' => $fingerprint, 'completed' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('production_idempotency_keys')->where('actor_id', $actorId)
            ->where('operation', $action)->where('key_hash', $keyHash)->lockForUpdate()->firstOrFail();
        if (! hash_equals($row->request_hash, $fingerprint)) {
            throw new ProductionException('idempotency_reused');
        }
        return $row;
    }

    public function finish(object $row): void
    {
        DB::table('production_idempotency_keys')->where('id', $row->id)
            ->update(['completed' => true, 'updated_at' => now()]);
    }

    private function sort(array &$data): void
    {
        if (! array_is_list($data)) ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) $this->sort($value);
        }
    }
}
