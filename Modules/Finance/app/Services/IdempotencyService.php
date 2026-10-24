<?php
namespace Modules\Finance\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Exceptions\FinanceException;

class IdempotencyService
{
    public function key(Request $request): string
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || strlen($key) < 1 || strlen($key) > 160) {
            throw ValidationException::withMessages(['Idempotency-Key' => __('finance.messages.idempotency_required')]);
        }
        return hash('sha256', $key);
    }

    /** Called inside a transaction. Existing requests return their original result, not a new posting. */
    public function claim(int $actorId, string $operation, string $keyHash, array $data): object
    {
        $this->sortRecursive($data);
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        DB::table('finance_idempotency_keys')->insertOrIgnore([
            'actor_id' => $actorId, 'operation' => $operation, 'key_hash' => $keyHash,
            'request_hash' => $fingerprint, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('finance_idempotency_keys')
            ->where('actor_id', $actorId)->where('operation', $operation)->where('key_hash', $keyHash)
            ->lockForUpdate()->firstOrFail();
        if (! hash_equals($row->request_hash, $fingerprint)) {
            throw new FinanceException('idempotency_reused');
        }
        return $row;
    }

    public function finish(object $claim, string $type, int $id): void
    {
        DB::table('finance_idempotency_keys')->where('id', $claim->id)->update([
            'result_type' => $type, 'result_id' => $id, 'updated_at' => now(),
        ]);
    }

    private function sortRecursive(array &$array): void
    {
        if (! array_is_list($array)) {
            ksort($array);
        }
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }
}
