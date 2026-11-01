<?php

namespace Modules\PrintingServices\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Enums\PricingMethod;

class PrintingService extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = ['name', 'base_price', 'cost_price', 'pricing_method', 'status'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:6',
            'cost_price' => 'decimal:2',
            'status' => PrintingServiceStatus::class,
            'pricing_method' => PricingMethod::class,
        ];
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(PrintingServicePriceTier::class);
    }
}

