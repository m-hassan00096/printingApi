<?php
namespace Modules\Customers\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Settings\Models\ParameterValue;

class Customer extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = ['name', 'phone', 'address', 'customer_type_id', 'status'];

    protected function casts(): array
    {
        return ['status' => CustomerStatus::class];
    }

    public function customerType(): BelongsTo
    {
        return $this->belongsTo(ParameterValue::class, 'customer_type_id')->withTrashed();
    }
}
