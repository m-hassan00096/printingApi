<?php
namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Orders\Models\Order;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Enums\PaymentMethod;

class Collection extends Model
{
    protected $table = 'finance_collections';
    protected $guarded = ['id'];
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id')->withTrashed();
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_method' => PaymentMethod::class, 'source' => CollectionSource::class,
            'status' => CollectionStatus::class, 'collected_on' => 'date:Y-m-d', 'reversed_at' => 'datetime'];
    }
}
