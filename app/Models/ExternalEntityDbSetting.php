<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalEntityDbSetting extends Model
{
    protected $table = 'ajustes_bd_entidades';

    protected $fillable = [
        'enabled',
        'driver',
        'host',
        'port',
        'username',
        'db_prefix',
        'code_padding',
        'table_name',
        'inventory_lookup_column',
        'areas_table',
        'area_code_column',
        'area_name_column',
        'area_column',
        'grupo_column',
        'subgrupo_column',
        'grupo_value',
        'subgrupo_value',
        'timeout',
        'last_synced_at',
        'last_sync_summary',
    ];

    protected $casts = [
        'enabled'        => 'boolean',
        'port'           => 'integer',
        'code_padding'   => 'integer',
        'grupo_value'    => 'integer',
        'subgrupo_value' => 'integer',
        'timeout'        => 'integer',
        'last_synced_at' => 'datetime',
        'last_sync_summary' => 'array',
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
            'enabled'        => false,
            'driver'         => 'mysql',
            'port'           => 3306,
            'db_prefix'        => 'r4_',
            'code_padding'     => 0,
            'table_name'               => 'activos',
            'inventory_lookup_column'  => 'codigo',
            'areas_table'      => 'areas_responsabilidad',
            'area_code_column' => 'codigo',
            'area_name_column' => 'nombre',
            'area_column'      => 'area_responsabilidad',
            'grupo_column'   => 'grupo',
            'subgrupo_column' => 'subgrupo',
            'grupo_value'    => 2,
            'subgrupo_value' => 3,
            'timeout'        => 5,
        ]);
    }
}
