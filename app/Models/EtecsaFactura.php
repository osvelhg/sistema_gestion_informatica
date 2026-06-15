<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class EtecsaFactura extends Model
{
    protected $table = 'etecsa_facturas';

    protected $fillable = [
        'numero_factura',
        'numero_cliente',
        'nombre_cliente',
        'periodo_desde',
        'periodo_hasta',
        'fecha_vencimiento',
        'codigo_pago_banco',
        'oficina_comercial',
        'zona_postal',
        'moneda',
        'tasa_cambio',
        'total_cuota_mensual',
        'total_consumo',
        'total_comision',
        'total_impuesto',
        'total_facturado',
        'total_saldo',
        'total_a_pagar',
        'total_usd',
        'tipo_factura',
        'pdf_hash',
        'imported_by',
    ];

    protected $casts = [
        'periodo_desde'       => 'date',
        'periodo_hasta'       => 'date',
        'fecha_vencimiento'   => 'date',
        'tasa_cambio'         => 'decimal:3',
        'total_cuota_mensual' => 'decimal:2',
        'total_consumo'       => 'decimal:2',
        'total_comision'      => 'decimal:2',
        'total_impuesto'      => 'decimal:2',
        'total_facturado'     => 'decimal:2',
        'total_saldo'         => 'decimal:2',
        'total_a_pagar'       => 'decimal:2',
        'total_usd'           => 'decimal:4',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function servicios(): HasMany
    {
        return $this->hasMany(EtecsaServicio::class, 'factura_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePeriodo(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('periodo_desde', [$desde, $hasta]);
    }

    public function scopeTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo_factura', $tipo);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('numero_factura', 'like', "%{$term}%")
              ->orWhere('numero_cliente', 'like', "%{$term}%")
              ->orWhere('nombre_cliente', 'like', "%{$term}%");
        });
    }
}
