<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LdapSetting extends Model
{
    protected $table = 'ajustes_ldap';

    protected $fillable = [
        'enabled',
        'host',
        'port',
        'base_dn',
        'bind_username',
        'use_ssl',
        'use_tls',
        'timeout',
        'user_search_base',
    ];

    // bind_password NO está en fillable — se maneja con mutator para forzar encriptación

    protected $casts = [
        'enabled'  => 'boolean',
        'use_ssl'  => 'boolean',
        'use_tls'  => 'boolean',
        'port'     => 'integer',
        'timeout'  => 'integer',
    ];

    protected $hidden = ['bind_password'];

    /**
     * Devuelve la fila única, creándola con valores por defecto si no existe.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'enabled'  => false,
            'port'     => 389,
            'use_ssl'  => false,
            'use_tls'  => false,
            'timeout'  => 5,
        ]);
    }

    /**
     * Encripta la contraseña antes de guardarla.
     * Si se pasa null o cadena vacía, se deja null (no sobreescribe con vacío).
     */
    public function setBindPasswordAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['bind_password'] = encrypt($value);
        }
        // Si viene vacío o null, no hacemos nada (conserva la anterior)
    }

    /**
     * Desencripta la contraseña al leerla.
     */
    public function getBindPasswordAttribute(?string $value): ?string
    {
        if ($value === null) return null;
        try {
            return decrypt($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Indica si ya hay contraseña guardada (sin revelarla).
     */
    public function getHasPasswordAttribute(): bool
    {
        return $this->attributes['bind_password'] !== null
            && $this->attributes['bind_password'] !== '';
    }
}
