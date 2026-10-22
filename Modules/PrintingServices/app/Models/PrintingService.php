<?php

namespace Modules\PrintingServices\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PrintingServices\Enums\PrintingServiceStatus;

class PrintingService extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = ['name', 'base_price', 'status'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'status' => PrintingServiceStatus::class,
        ];
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(PrintingServicePriceTier::class);
    }
}

