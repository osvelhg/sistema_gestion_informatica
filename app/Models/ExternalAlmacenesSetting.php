<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalAlmacenesSetting extends Model
{
    protected $table = 'ajustes_almacenes_externos';

    protected $fillable = [
        'enabled',
        'host',
        'port',
        'username',
        'database_name',
        'table_name',
        'schema_name',
        'id_unidad_column',
        'almacen_column',
        'id_piso_column',
        'id_almacen_pk_column',
        'import_solo_abierto',
        'import_tipos',
        'sync_creates_areas',
        'timeout',
        'last_synced_at',
        'last_sync_summary',
    ];

    protected $casts = [
        'enabled'            => 'boolean',
        'port'               => 'integer',
        'import_solo_abierto'=> 'boolean',
        'import_tipos'       => 'array',
        'sync_creates_areas' => 'boolean',
        'timeout'            => 'integer',
        'last_synced_at'     => 'datetime',
        'last_sync_summary'  => 'array',
    ];

    // Flags de tipo disponibles en la tabla Almacenes
    public const TIPO_FLAGS = [
        'MercanciaVenta',
        'Exhibicion',
        'Interno',
        'Gastronomia',
        'Insumo',
        'Inversiones',
        'Boutique',
        'Consignacion',
        'Emergente',
        'Distribuir',
    ];

    public function setPasswordAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['password'] = encrypt($value);
        }
    }

    public function getPasswordAttribute(): ?string
    {
        $raw = $this->attributes['password'] ?? null;
        if (! $raw) {
            return null;
        }
        try {
            return decrypt($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getHasPasswordAttribute(): bool
    {
        return ! empty($this->attributes['password']);
    }

    public static function current(): static
    {
        return static::firstOrCreate([], [
            'enabled'              => false,
            'port'                 => 1433,
            'database_name'        => 'UnidadesComerciales',
            'table_name'           => 'Almacenes',
            'schema_name'          => 'dbo',
            'id_unidad_column'     => 'IdUnidad',
            'almacen_column'       => 'Almacen',
            'id_piso_column'       => 'IdPiso',
            'id_almacen_pk_column' => 'IdGerenciaIdAlmacen',
            'import_solo_abierto'  => true,
            'import_tipos'         => ['MercanciaVenta', 'Exhibicion'],
            'sync_creates_areas'   => true,
            'timeout'              => 10,
        ]);
    }
}
