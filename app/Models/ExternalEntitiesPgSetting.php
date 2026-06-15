<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalEntitiesPgSetting extends Model
{
    protected $table = 'ajustes_entidades_externas';

    protected $fillable = [
        'driver',
        'enabled',
        'host',
        'port',
        'database_name',
        'schema_name',
        'username',
        'table_name',
        'name_column',
        'code_column',
        'municipio_code_column',
        'provincia_column',
        'timeout',
        'last_synced_at',
        'last_sync_summary',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
        'timeout' => 'integer',
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

    public function tableMappings(): HasMany
    {
        return $this->hasMany(ExternalEntitiesPgTableMapping::class, 'external_entities_pg_setting_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public static function current(): static
    {
        return static::firstOrCreate([], [
            'driver' => 'pgsql',
            'enabled' => false,
            'port' => 5432,
            'schema_name' => 'public',
            'table_name' => 'entities',
            'name_column' => 'name',
            'code_column' => 'code',
            'timeout' => 5,
        ]);
    }
}
