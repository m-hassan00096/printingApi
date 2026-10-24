<?php
namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Production\Enums\ProductionEventType;

class ProductionItemEvent extends Model
{
    protected $table = 'production_item_events';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event_type' => ProductionEventType::class, 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Production events are immutable.'));
        static::deleting(fn () => throw new LogicException('Production events cannot be deleted.'));
    }
}
