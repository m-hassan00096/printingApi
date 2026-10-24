<?php
namespace Modules\Orders\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Orders\Enums\OrderItemStage;
use Modules\PrintingServices\Models\PrintingService;

class OrderItem extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = ['quantity', 'specifications', 'agreed_total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'agreed_total' => 'decimal:2',
            'production_stage' => OrderItemStage::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function printingService(): BelongsTo
    {
        return $this->belongsTo(PrintingService::class, 'printing_service_id')->withTrashed();
    }

    public function files(): HasMany
    {
        return $this->hasMany(OrderItemFile::class);
    }
}
