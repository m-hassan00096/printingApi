<?php

namespace Modules\Settings\Models;

use App\Models\Concerns\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParameterValue extends Model
{
    use SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'parameter_id',
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }
}
