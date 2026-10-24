<?php
namespace Modules\Orders\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customers\Models\Customer;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderSource;
use Modules\Orders\Enums\OrderStatus;

class Order extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = ['source', 'notes', 'due_date'];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'production_status' => OrderProductionStatus::class,
            'due_date' => 'date:Y-m-d',
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'delivered_at' => 'datetime',
            'delivered_local_date' => 'date:Y-m-d',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class);
    }
}
