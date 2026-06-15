<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtecsaServicio extends Model
{
    protected $table = 'etecsa_servicios';

    public const MATCH_CONNECTIVITY = 'connectivity';

    public const MATCH_TELEFONIA_PISO = 'telefonia_piso';

    public const MATCH_TELEFONIA_DEPARTAMENTO = 'telefonia_departamento';

    protected $fillable = [
        'factura_id',
        'connectivity_record_id',
        'sales_floor_id',
        'department_id',
        'match_source',
        'numero_servicio',
        'cuota_facturada',
        'consumo',
        'comision',
        'impuesto',
        'total_servicio',
    ];

    protected $casts = [
        'cuota_facturada' => 'decimal:2',
        'consumo'         => 'decimal:2',
        'comision'        => 'decimal:2',
        'impuesto'        => 'decimal:2',
        'total_servicio'  => 'decimal:2',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function factura(): BelongsTo
    {
        return $this->belongsTo(EtecsaFactura::class, 'factura_id');
    }

    public function connectivityRecord(): BelongsTo
    {
        return $this->belongsTo(ConnectivityRecord::class, 'connectivity_record_id');
    }

    /**
     * Piso de venta vinculado directamente (telefonía fija por número, sin registro de conectividad).
     */
    public function salesFloorDirect(): BelongsTo
    {
        return $this->belongsTo(SalesFloor::class, 'sales_floor_id');
    }

    /**
     * Departamento / oficina (teléfono fijo en catálogo de departamentos).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function cuotasDetalle(): HasMany
    {
        return $this->hasMany(EtecsaCuotaDetalle::class, 'servicio_id');
    }

    public function trafico(): HasMany
    {
        return $this->hasMany(EtecsaTrafico::class, 'servicio_id');
    }

    public function llamadas(): HasMany
    {
        return $this->hasMany(EtecsaLlamada::class, 'servicio_id');
    }

    // ─── Accesorios (derivados de ConnectivityRecord) ─────────────────────────

    /**
     * Número de servicio efectivo: desde ConnectivityRecord o fallback local.
     */
    public function getNumeroServicioEfectivoAttribute(): ?string
    {
        return $this->connectivityRecord?->id_facturacion ?? $this->numero_servicio;
    }

    /**
     * Tipo de servicio derivado de tipo_enlace del registro de conectividad.
     */
    public function getTipoServicioAttribute(): ?string
    {
        return $this->connectivityRecord?->tipo_enlace;
    }

    /**
     * Descripción del servicio (velocidad/tipo).
     */
    public function getDescripcionServicioAttribute(): ?string
    {
        return $this->connectivityRecord?->velocidad_etecsa;
    }

    /**
     * Piso de venta: primero por conectividad, si no por vínculo directo telefonía.
     */
    public function getSalesFloorAttribute(): ?SalesFloor
    {
        $viaConn = $this->connectivityRecord?->salesFloor;
        if ($viaConn) {
            return $viaConn;
        }
        if ($this->sales_floor_id) {
            return $this->relationLoaded('salesFloorDirect')
                ? $this->getRelation('salesFloorDirect')
                : $this->salesFloorDirect()->first();
        }

        return null;
    }

    /**
     * Etiqueta corta para listados (piso de venta u oficina / departamento).
     */
    public function getUbicacionLabelAttribute(): string
    {
        if ($this->connectivityRecord?->salesFloor) {
            return $this->connectivityRecord->salesFloor->name;
        }
        if ($this->sales_floor_id) {
            $sf = $this->relationLoaded('salesFloorDirect')
                ? $this->getRelation('salesFloorDirect')
                : $this->salesFloorDirect()->first();

            return $sf?->name ?? '—';
        }
        if ($this->department_id) {
            $d = $this->relationLoaded('department')
                ? $this->getRelation('department')
                : $this->department()->first();

            return $d ? 'Oficina · '.$d->name : '—';
        }

        return '—';
    }

    /**
     * Diferencia entre cuota facturada y cuota registrada en el catálogo.
     * Positivo = ETECSA cobró más. Negativo = cobró menos.
     */
    public function getDiferenciaAttribute(): float
    {
        $cuotaCatalogo = (float) ($this->connectivityRecord?->cuota ?? 0);
        return (float) $this->cuota_facturada - $cuotaCatalogo;
    }
}
