<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdslMode extends Model
{
    protected $table = 'modos_adsl';

    protected $fillable = ['code', 'nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Devuelve el ID del modo dado su code; si no existe y $autoCreate=true lo crea.
     */
    public static function findOrCreateByCode(string $code): static
    {
        $code = strtoupper(trim($code));

        return static::firstOrCreate(
            ['code' => $code],
            ['nombre' => $code, 'activo' => true]
        );
    }
}
