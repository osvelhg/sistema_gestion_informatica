<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $table = 'marcas';

    protected $fillable = ['name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function componentModels(): HasMany
    {
        return $this->hasMany(ComponentModel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
