<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaVentaFuente extends Model
{
    protected $table = 'area_venta_fuente';

    protected $fillable = [
        'area_venta_id',
        'fuente_id',
        'canal_key',
        'canal_electronico_id',
    ];

    public function areaVenta(): BelongsTo
    {
        return $this->belongsTo(AreaVenta::class);
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(DatacellSource::class, 'fuente_id');
    }
}
