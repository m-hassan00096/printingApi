<?php
namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Finance\Enums\LedgerEntryType;

class LedgerEntry extends Model
{
    protected $table = 'finance_ledger_entries';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Corrections are new entries, never edits or deletion of posted movements.
        static::updating(fn () => throw new LogicException('Posted ledger movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted ledger movements cannot be deleted.'));
    }
    protected function casts(): array
    {
        return ['type' => LedgerEntryType::class, 'signed_amount' => 'decimal:2', 'effective_date' => 'date:Y-m-d', 'created_at' => 'datetime'];
    }
}
