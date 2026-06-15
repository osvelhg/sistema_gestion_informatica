<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provincia extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'sigla_2', 'sigla_3', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
