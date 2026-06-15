<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstablishmentStatus extends Model
{
    protected $table = 'estados_establecimiento';

    protected $fillable = ['name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function salesFloors(): HasMany
    {
        return $this->hasMany(SalesFloor::class);
    }
}
