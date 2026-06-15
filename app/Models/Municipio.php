<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Municipio extends Model
{
    use SoftDeletes;

    protected $fillable = ['provincia_id', 'provincia_code', 'name', 'code', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
