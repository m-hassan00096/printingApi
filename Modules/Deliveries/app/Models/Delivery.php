<?php
namespace Modules\Deliveries\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Deliveries\Enums\DeliveryStatus;

class Delivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'delivered_at' => 'datetime', 'delivered_local_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }
}
