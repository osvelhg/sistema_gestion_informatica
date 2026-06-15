<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AreaVenta extends Model
{
    protected $table = 'areas_venta';

    protected $fillable = [
        'sales_floor_id',
        'name',
        'tpv_boxes',
        'pos_phone_qty',
        'pos_ip_qty',
        'pos_ip_demand',
        'pos_gprs_qty',
        'pos_gprs_demand',
        'has_ip_connectivity',
        'broken_pos_qty',
        'cash_register_model_code',
        'pos_currency_mlc',
        'pos_currency_cup',
        'qr_fincimex_mlc',
        'qr_fincimex_cup',
        'src_fincimex_mlc',
        'src_fincimex_cup',
        'terminal_id',
        'terminal_ip',
        // Campos de Almacenes (BD externa contable — fuente autoritativa de estructura)
        'almacen_id',
        'id_almacen_local',
        'almacen_tipo',
        'almacen_abierto',
        'almacen_mlc',
        'almacen_e_contable',
        'almacen_exhibicion',
        'almacen_interno',
        'almacen_merma',
        'almacen_gastronomia',
        'almacen_insumo',
        'almacen_inversiones',
        'almacen_boutique',
        'almacen_merma_origen',
        'almacen_consignacion',
        'almacen_emergente',
        'almacen_despacho_div',
        'almacen_distribuir',
        'almacen_mercancia_venta',
    ];

    protected $casts = [
        'has_ip_connectivity' => 'boolean',
        'pos_currency_mlc'    => 'boolean',
        'pos_currency_cup'    => 'boolean',
        'qr_fincimex_mlc'     => 'boolean',
        'qr_fincimex_cup'     => 'boolean',
        // Almacenes
        'almacen_id'              => 'integer',
        'id_almacen_local'        => 'integer',
        'almacen_abierto'         => 'boolean',
        'almacen_mlc'             => 'boolean',
        'almacen_exhibicion'      => 'boolean',
        'almacen_interno'         => 'boolean',
        'almacen_merma'           => 'boolean',
        'almacen_gastronomia'     => 'boolean',
        'almacen_insumo'          => 'boolean',
        'almacen_inversiones'     => 'boolean',
        'almacen_boutique'        => 'boolean',
        'almacen_merma_origen'    => 'boolean',
        'almacen_consignacion'    => 'boolean',
        'almacen_emergente'       => 'boolean',
        'almacen_despacho_div'    => 'boolean',
        'almacen_distribuir'      => 'boolean',
        'almacen_mercancia_venta' => 'boolean',
    ];

    public const CASH_REGISTER_MODELS = [
        1 => 'Casio',
        2 => 'Óptima',
        3 => 'Apos04',
        4 => 'Apos05',
        5 => 'PC',
    ];

    public function salesFloor(): BelongsTo
    {
        return $this->belongsTo(SalesFloor::class);
    }

    /**
     * Fuentes QR (datacell) vinculadas; a lo sumo una por canal electrónico en esta área.
     *
     * @return BelongsToMany<DatacellSource, self>
     */
    public function datacellSources(): BelongsToMany
    {
        return $this->belongsToMany(DatacellSource::class, 'area_venta_fuente', 'area_venta_id', 'fuente_id')
            ->withPivot('id', 'canal_key', 'canal_electronico_id')
            ->withTimestamps();
    }
}
