<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComponentType extends Model
{
    protected $table = 'tipos_componentes';

    protected $fillable = ['slug', 'name', 'category', 'active'];

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
