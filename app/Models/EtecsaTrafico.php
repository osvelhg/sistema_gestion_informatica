<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EtecsaTrafico extends Model
{
    protected $table = 'etecsa_trafico';

    protected $fillable = [
        'servicio_id',
        'categoria',
        'subcategoria',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(EtecsaServicio::class, 'servicio_id');
    }

    public function scopeCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }
}
