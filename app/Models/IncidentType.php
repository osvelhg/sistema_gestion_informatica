<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentType extends Model
{
    protected $table = 'tipos_incidentes';

    protected $fillable = ['name', 'slug', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function seals(): HasMany
    {
        return $this->hasMany(Seal::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
