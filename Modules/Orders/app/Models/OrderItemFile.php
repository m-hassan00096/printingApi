<?php
namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\Models\User;

class OrderItemFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk', 'path', 'original_name', 'extension', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
