<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DatacellSource extends Model
{
    protected $table = 'fuentes';

    protected $fillable = [
        'external_id',
        'sales_floor_id',
        'source',
        'source_name',
        'moneda',
        'canal_electronico_id',
        'tipo_fuente_id',
        'id_unidad',
        'unidad_nombre',
        'activo',
        'id_piso',
        'id_division',
        'division',
        'synced_at',
    ];

    protected $casts = [
        'activo'               => 'boolean',
        'synced_at'            => 'datetime',
        'external_id'          => 'integer',
        'id_unidad'            => 'integer',
        'canal_electronico_id' => 'integer',
        'tipo_fuente_id'       => 'integer',
        'id_piso'              => 'integer',
        'id_division'          => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function salesFloor(): BelongsTo
    {
        return $this->belongsTo(SalesFloor::class, 'sales_floor_id');
    }

    public function canalElectronico(): BelongsTo
    {
        return $this->belongsTo(CanalElectronico::class, 'canal_electronico_id');
    }

    public function tipoFuente(): BelongsTo
    {
        return $this->belongsTo(TipoFuente::class, 'tipo_fuente_id');
    }

    public function trabajadores(): BelongsToMany
    {
        return $this->belongsToMany(Trabajador::class, 'fuente_trabajador', 'source_id', 'trabajador_id')
            ->withPivot('id', 'rolqr_id', 'fecha_alta', 'fecha_baja', 'estado')
            ->withTimestamps();
    }

    public function areasVenta(): BelongsToMany
    {
        return $this->belongsToMany(AreaVenta::class, 'area_venta_fuente', 'fuente_id', 'area_venta_id')
            ->withPivot('id', 'canal_key', 'canal_electronico_id')
            ->withTimestamps();
    }

    public function salesFloorsPiso(): BelongsToMany
    {
        return $this->belongsToMany(SalesFloor::class, 'piso_venta_fuente', 'fuente_id', 'sales_floor_id')
            ->withPivot('id', 'canal_key', 'canal_electronico_id')
            ->withTimestamps();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function scopeByMoneda(Builder $q, string $moneda): Builder
    {
        return $q->where('moneda', strtoupper($moneda));
    }

    public function scopeByCanal(Builder $q, int $canalId): Builder
    {
        return $q->where('canal_electronico_id', $canalId);
    }

    public function scopeByTipo(Builder $q, int $tipoId): Builder
    {
        return $q->where('tipo_fuente_id', $tipoId);
    }

    public function scopeByUnidad(Builder $q, int $idUnidad): Builder
    {
        return $q->where('id_unidad', $idUnidad);
    }

    /**
     * Alinea fuentes.sales_floor_id con los vínculos en area_venta_fuente / piso_venta_fuente.
     * Así el listado «Códigos QR» muestra el piso aunque el enlace se haya hecho solo desde Áreas / QR piso.
     * Si no hay vínculos QR, no modifica el campo (puede estar asignado solo desde «Vincular piso» en Códigos QR).
     */
    public function refreshSalesFloorIdFromQrPivots(): void
    {
        $this->unsetRelation('areasVenta');
        $this->unsetRelation('salesFloorsPiso');
        $this->load(['areasVenta:id,sales_floor_id', 'salesFloorsPiso:id']);

        $fromArea = $this->areasVenta->first()?->sales_floor_id;
        if ($fromArea) {
            if ((int) $this->sales_floor_id !== (int) $fromArea) {
                $this->forceFill(['sales_floor_id' => (int) $fromArea])->saveQuietly();
            }

            return;
        }

        $fromPiso = $this->salesFloorsPiso->first()?->id;
        if ($fromPiso) {
            if ((int) $this->sales_floor_id !== (int) $fromPiso) {
                $this->forceFill(['sales_floor_id' => (int) $fromPiso])->saveQuietly();
            }
        }
    }
}
