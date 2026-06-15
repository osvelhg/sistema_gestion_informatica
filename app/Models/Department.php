<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $table = 'departamentos';

    protected $fillable = ['entity_id', 'name', 'telefono', 'code', 'codigo_area', 'codigo_entidad', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function equipmentFiles(): HasMany
    {
        return $this->hasMany(EquipmentFile::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
