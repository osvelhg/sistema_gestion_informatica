<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ContractedSpeed;

trait ExcelImportHelpers
{
    protected function toInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) preg_replace('/[^0-9]/', '', (string) $value);
    }

    /**
     * Convierte un valor de celda Excel a string de coordenada limpio.
     */
    protected function toCoordString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        if (is_int($value) && abs($value) > 180) {
            foreach ([1_000_000, 10_000_000, 100_000, 1_000] as $divisor) {
                $normalized = $value / $divisor;
                if (abs($normalized) <= 180) {
                    return (string) $normalized;
                }
            }

            return (string) $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        $str = trim((string) $value);

        if (str_contains($str, ',') && !str_contains($str, '.')) {
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '', $str);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $str);

        return is_numeric($clean) ? $clean : null;
    }

    /**
     * Normaliza un nombre para comparación tolerante a tildes y mayúsculas.
     */
    protected static function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return (string) preg_replace('/\s+/', ' ', $value);
    }

    /**
     * @return array{0: ?string, 1: ?int} [ipv4, cidr 8-32]
     */
    protected function parseIpAndCidr(string $raw): array
    {
        if ($raw === '') {
            return [null, null];
        }

        if (preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $raw, $m)) {
            $bits = (int) $m[2];

            return ($bits >= 8 && $bits <= 32) && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ? [$m[1], $bits]
                : [null, null];
        }

        if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [$raw, null];
        }

        return [null, null];
    }

    /**
     * Resuelve una cadena de velocidad contra el nomenclador.
     *
     * @param  array<string, string> &$knownNames  mapa lowercased → nombre canónico
     * @param  bool                   $persistNew   si false (preview), no inserta en BD
     */
    protected function resolveSpeed(string $raw, array &$knownNames, bool $persistNew = true): ?string
    {
        if ($raw === '') {
            return null;
        }

        $key = strtolower(trim($raw));

        if (isset($knownNames[$key])) {
            return $knownNames[$key];
        }

        if (!$persistNew) {
            return null;
        }

        ContractedSpeed::create(['nombre' => $raw, 'kbps' => null, 'activo' => true]);
        $knownNames[$key] = $raw;

        return $raw;
    }

    /**
     * Extrae la primera IP válida de un string que puede contener texto adicional.
     */
    protected function extractIp(?string $raw): ?string
    {
        $parsed = $this->extractIpAndCidr($raw);

        return $parsed['ip'];
    }

    /**
     * IP y segmento CIDR desde celda Excel (ej. 172.16.17.172/30 o 10.146.74.0/24).
     *
     * @return array{ip: ?string, cidr: ?string} cidr en formato host/prefijo o red/prefijo
     */
    protected function extractIpAndCidr(?string $raw): array
    {
        if ($raw === null) {
            return ['ip' => null, 'cidr' => null];
        }

        $s = trim($raw);
        if ($s === '') {
            return ['ip' => null, 'cidr' => null];
        }

        if (preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})\s*$#', $s, $m)) {
            $ip = $m[1];
            $p = (int) $m[2];
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $p < 0 || $p > 32) {
                return ['ip' => null, 'cidr' => null];
            }

            return ['ip' => $ip, 'cidr' => $ip.'/'.$p];
        }

        if (preg_match('/(\d{1,3}(?:\.\d{1,3}){3})/', $s, $m)) {
            $ip = $m[1];

            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ? ['ip' => $ip, 'cidr' => null]
                : ['ip' => null, 'cidr' => null];
        }

        return ['ip' => null, 'cidr' => null];
    }
}
