<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectivityRecord extends Model
{
    protected $table = 'registros_conectividad';

    protected $casts = [
        'cuota' => 'decimal:2',
    ];

    protected $fillable = [
        'sales_floor_id',
        'unit_name',
        'address',
        'source_sheet',
        'contracted_speed',
        'notes',
        'tipo_enlace',
        'ed',
        'ina',
        'id_facturacion',
        'velocidad_etecsa',
        'cuota',
        'ip_wan',
        'wan_cidr',
        'ip_lan',
        'lan_cidr',
    ];

    public function salesFloor(): BelongsTo
    {
        return $this->belongsTo(SalesFloor::class);
    }

    public function etecsaServicios(): HasMany
    {
        return $this->hasMany(EtecsaServicio::class, 'connectivity_record_id');
    }

}
