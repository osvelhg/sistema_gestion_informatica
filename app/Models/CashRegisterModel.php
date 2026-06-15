<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CashRegisterModel extends Model
{
    protected $table = 'modelos_caja';

    protected $fillable = ['code', 'name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function salesFloors(): BelongsToMany
    {
        return $this->belongsToMany(SalesFloor::class, 'piso_venta_modelo_caja')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
