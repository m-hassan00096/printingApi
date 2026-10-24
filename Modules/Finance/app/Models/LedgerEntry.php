<?php
namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Enums\LedgerEntryType;

class LedgerEntry extends Model
{
    protected $table = 'finance_ledger_entries';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['type' => LedgerEntryType::class, 'signed_amount' => 'decimal:2', 'effective_date' => 'date:Y-m-d', 'created_at' => 'datetime'];
    }
}
