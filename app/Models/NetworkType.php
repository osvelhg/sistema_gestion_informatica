<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkType extends Model
{
    protected $table = 'tipos_red';

    protected $fillable = ['name', 'color', 'active'];

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
