<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entity extends Model
{
    use SoftDeletes;

    protected $table = 'entidades';

    protected $fillable = ['municipio_id', 'municipio_code', 'name', 'code', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function equipmentFiles(): HasMany
    {
        return $this->hasMany(EquipmentFile::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_entidades')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
