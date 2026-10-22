<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parameter extends Model
{
    protected $fillable = [
        'name',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ParameterValue::class);
    }
}
