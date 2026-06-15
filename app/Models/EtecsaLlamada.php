<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtecsaLlamada extends Model
{
    protected $table = 'etecsa_llamadas';

    // Tabla de hechos: sin timestamps para ahorrar espacio.
    // Siempre se inserta en lote con insert() en chunks de 500.
    public $timestamps = false;

    protected $fillable = [
        'servicio_id',
        'fecha',
        'hora',
        'lugar',
        'destino',
        'duracion',
        'importe',
    ];

    protected $casts = [
        'fecha'   => 'date',
        'importe' => 'decimal:4',
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(EtecsaServicio::class, 'servicio_id');
    }
}
