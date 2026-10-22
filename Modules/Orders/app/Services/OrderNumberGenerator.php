<?php
namespace Modules\Orders\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    /** Invoke inside the transaction that also durably posts Finance movements. */
    public function next(): string
    {
        $day = CarbonImmutable::now('Africa/Cairo')->format('Y-m-d');

        DB::table('order_daily_sequences')->insertOrIgnore([
            'day' => $day, 'last_number' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sequence = DB::table('order_daily_sequences')
            ->where('day', $day)->lockForUpdate()->first();

        $next = (int) $sequence->last_number + 1;
        DB::table('order_daily_sequences')->where('day', $day)->update([
            'last_number' => $next, 'updated_at' => now(),
        ]);

        return 'ORD-'.CarbonImmutable::parse($day)->format('ymd')
            .str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
