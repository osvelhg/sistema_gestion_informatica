<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentModel extends Model
{
    protected $table = 'modelos_componentes';

    protected $fillable = ['component_type_id', 'brand_id', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
