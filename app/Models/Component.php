<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Component extends Model
{
    protected $table = 'componentes';

    protected $fillable = [
        'equipment_file_id', 'category', 'type', 'custom_name',
        'brand', 'model', 'inventory_number', 'serial_number', 'status',
    ];

    // Tipos fijos por categoría
    public const CARACTERISTICAS = ['motherboard', 'cpu', 'ram', 'power_supply', 'reader', 'hdd'];
    public const PERIFERICOS = ['monitor', 'keyboard', 'mouse', 'speakers', 'printer', 'scanner', 'backup'];
    public const DISPOSITIVOS = ['custom_1', 'custom_2'];

    // Labels en español
    public const LABELS = [
        'motherboard'  => 'Tarjeta Madre',
        'cpu'          => 'Microprocesador',
        'ram'          => 'Memoria RAM',
        'power_supply' => 'Fuente de Alimentación',
        'reader'       => 'Lector',
        'hdd'          => 'Disco Duro',
        'monitor'      => 'Monitor',
        'keyboard'     => 'Teclado',
        'mouse'        => 'Mouse',
        'speakers'     => 'Bocinas',
        'printer'      => 'Impresora',
        'scanner'      => 'Scanner',
        'backup'       => 'Backup',
        'custom_1'     => 'Dispositivo 1',
        'custom_2'     => 'Dispositivo 2',
    ];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class, 'type', 'slug');
    }

    public function getLabelAttribute(): string
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }

        if ($this->relationLoaded('componentType') && $this->componentType?->name) {
            return $this->componentType->name;
        }

        return self::LABELS[$this->type] ?? Str::headline(str_replace('-', ' ', $this->type));
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
