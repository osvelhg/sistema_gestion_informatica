<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractedSpeed extends Model
{
    protected $table = 'velocidades_contratadas';

    protected $fillable = ['nombre', 'kbps', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function connectivityRecords(): HasMany
    {
        return $this->hasMany(ConnectivityRecord::class, 'contracted_speed', 'nombre');
    }

    /**
     * Busca por nombre exacto (case-insensitive). Si no existe lo crea con el
     * valor tal como viene del Excel (normalizado con trim).
     */
    public static function findOrCreateByNombre(string $nombre): static
    {
        $nombre = trim($nombre);

        // Búsqueda case-insensitive para evitar duplicados por capitalización
        $existing = static::whereRaw('LOWER(nombre) = LOWER(?)', [$nombre])->first();

        if ($existing) {
            return $existing;
        }

        return static::create(['nombre' => $nombre, 'kbps' => null, 'activo' => true]);
    }
}
