<?php

namespace Modules\PrintingServices\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Settings\Models\ParameterValue;

class PrintingServicePriceTier extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'customer_type_id', 'min_quantity', 'max_quantity', 'price',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function printingService(): BelongsTo
    {
        return $this->belongsTo(PrintingService::class);
    }

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(ParameterValue::class, 'customer_type_id');
    }
}

