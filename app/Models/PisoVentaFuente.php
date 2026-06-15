<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PisoVentaFuente extends Model
{
    protected $table = 'piso_venta_fuente';

    protected $fillable = [
        'sales_floor_id',
        'fuente_id',
        'canal_key',
        'canal_electronico_id',
    ];

    public function salesFloor(): BelongsTo
    {
        return $this->belongsTo(SalesFloor::class);
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(DatacellSource::class, 'fuente_id');
    }
}
