<?php
namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Enums\PaymentMethod;

class Refund extends Model
{
    protected $table = 'finance_refunds';
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_method' => PaymentMethod::class, 'refunded_on' => 'date:Y-m-d'];
    }
}
