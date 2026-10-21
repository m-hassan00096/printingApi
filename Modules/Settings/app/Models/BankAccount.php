<?php

namespace Modules\Settings\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Settings\Enums\PaymentDestinationStatus;

class BankAccount extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'bank_name',
        'account_number',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'status' => PaymentDestinationStatus::class,
        ];
    }
}
