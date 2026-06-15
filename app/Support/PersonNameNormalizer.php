<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalización solo para comparar duplicados (mayúsculas, sin tildes vía ASCII).
 * El nombre almacenado en BD conserva el formato original del CI externo o del AD.
 */
class PersonNameNormalizer
{
    public static function fingerprint(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '';
        }

        $ascii = Str::ascii(trim($name));
        $ascii = preg_replace('/[^a-zA-Z0-9\s]/u', '', $ascii) ?? '';
        $ascii = preg_replace('/\s+/u', ' ', trim($ascii)) ?? '';

        return Str::upper($ascii);
    }
}
