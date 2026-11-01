<?php
namespace Modules\Orders\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Orders\Enums\OrderItemStage;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Enums\PricingMethod;
use Modules\Identity\Models\User;

class OrderItem extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = ['quantity', 'specifications', 'agreed_total', 'pricing_method', 'width_cm', 'height_cm', 'pricing_rate_snapshot'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'pricing_method' => PricingMethod::class,
            'width_cm' => 'decimal:4',
            'height_cm' => 'decimal:4',
            'pricing_rate_snapshot' => 'decimal:6',
            'agreed_total' => 'decimal:2',
            'unit_cost_snapshot' => 'decimal:2',
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

    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id')->withTrashed();
    }

    public function files(): HasMany
    {
        return $this->hasMany(OrderItemFile::class);
    }
}
