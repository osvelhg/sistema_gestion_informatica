<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtecsaCuotaDetalle extends Model
{
    protected $table = 'etecsa_cuotas_detalle';

    protected $fillable = [
        'servicio_id',
        'concepto',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(EtecsaServicio::class, 'servicio_id');
    }
}
