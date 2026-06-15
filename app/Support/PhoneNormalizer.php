<?php

namespace App\Support;

/**
 * Normalización de teléfonos para emparejar N° de servicio ETECSA (telefonía fija)
 * con números guardados en pisos de venta y departamentos.
 */
final class PhoneNormalizer
{
    /**
     * Solo dígitos, sin prefijo internacional común ni ceros iniciales redundantes.
     */
    public static function digits(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return null;
        }
        // Cuba +53
        if (strlen($d) > 8 && str_starts_with($d, '53')) {
            $d = substr($d, 2);
        }
        // 00 prefijo internacional
        if (str_starts_with($d, '00') && strlen($d) > 10) {
            $d = substr($d, 2);
        }
        $d = ltrim($d, '0');

        return strlen($d) >= 6 ? $d : null;
    }

    /**
     * Claves candidatas para buscar en mapas (completo y últimos 8 dígitos, típico Cuba).
     *
     * @return list<string>
     */
    public static function matchKeys(?string $raw): array
    {
        $d = self::digits($raw);
        if ($d === null) {
            return [];
        }
        $keys = [$d];
        if (strlen($d) >= 8) {
            $suffix = substr($d, -8);
            if ($suffix !== $d) {
                $keys[] = $suffix;
            }
        }

        return array_values(array_unique($keys));
    }
}
