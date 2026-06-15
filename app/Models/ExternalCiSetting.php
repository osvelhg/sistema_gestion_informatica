<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCiSetting extends Model
{
    protected $table = 'ajustes_ci_externo';

    protected $fillable = [
        'enabled',
        'odbc_dsn',
        'host',
        'port',
        'database_name',
        'username',
        'table_name',
        'ci_column',
        'nombre_column',
        'apellido1_column',
        'apellido2_column',
        'telefono_column',
        'direccion_columns',
        'timeout',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port'    => 'integer',
        'direccion_columns' => 'array',
        'timeout' => 'integer',
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
        if (!$raw) {
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
        return !empty($this->attributes['password']);
    }

    public static function current(): static
    {
        return static::firstOrCreate([], [
            'enabled'          => false,
            'port'             => 1433,
            'table_name'       => 'T_EXPORTARTRABAJADORES',
            'ci_column'        => 'UT_ID',
            'nombre_column'    => 'UT_NOMBRE',
            'apellido1_column' => 'UT_APELLIDO1',
            'apellido2_column' => 'UT_APELLIDO2',
            'telefono_column'  => '',
            'direccion_columns' => ['UT_CALLE', 'UT_NO', 'UT_APTO', 'UT_ETRE', 'UT_RPTO'],
            'timeout'          => 5,
        ]);
    }
}
