<?php

namespace App\Services;

use App\Models\ExternalCiSetting;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;

class ExternalCiService
{
    private const CONNECTION_NAME = 'sqlsrv_ci';

    public static function testConnection(ExternalCiSetting $cfg): array
    {
        try {
            $conn = static::makeConnection($cfg);
            $conn->select('SELECT 1 AS ok');

            return [
                'success' => true,
                'message' => 'Conexión exitosa con SQL Server.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión SQL Server: ' . $e->getMessage(),
            ];
        }
    }

    public static function findByCi(string $ci, ?ExternalCiSetting $cfg = null): array
    {
        $cfg ??= ExternalCiSetting::current();

        if (! $cfg->enabled) {
            return [
                'success' => false,
                'message' => 'La búsqueda externa por CI está deshabilitada.',
            ];
        }

        try {
            $conn = static::makeConnection($cfg);

            $direccionColumns = collect($cfg->direccion_columns ?? [])->filter()->values()->all();

            $allColumns = collect([
                $cfg->ci_column,
                $cfg->nombre_column,
                $cfg->apellido1_column,
                $cfg->apellido2_column,
                ...$direccionColumns,
            ])->filter()->unique()->values();

            $row = $conn
                ->table($cfg->table_name)
                ->select($allColumns->all())
                ->where($cfg->ci_column, $ci)
                ->first();

            if (! $row) {
                return [
                    'success' => false,
                    'message' => 'No se encontró trabajador para ese CI.',
                ];
            }

            $nombre = trim(implode(' ', array_filter([
                $row->{$cfg->nombre_column}    ?? null,
                $row->{$cfg->apellido1_column} ?? null,
                $row->{$cfg->apellido2_column} ?? null,
            ])));

            $direccion = trim(implode(', ', array_filter(
                array_map(fn ($col) => $row->{$col} ?? null, $direccionColumns),
                fn ($v) => ! is_null($v) && trim((string) $v) !== ''
            )));

            return [
                'success' => true,
                'trabajador' => [
                    'ci'        => (string) ($row->{$cfg->ci_column} ?? $ci),
                    'nombre'    => $nombre,
                    'direccion' => $direccion,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error al consultar CI en SQL Server: ' . $e->getMessage(),
            ];
        }
    }

    public static function getTableColumns(ExternalCiSetting $cfg): array
    {
        $conn = static::makeConnection($cfg);

        return $conn
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_NAME', $cfg->table_name)
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->values()
            ->all();
    }

    /**
     * Crea la conexión directamente via PDO_ODBC — probado y funcionando
     * en PHP 8.3 FPM con DSN sql2008 definido en /etc/odbc.ini.
     */
    private static function makeConnection(ExternalCiSetting $cfg): SqlServerConnection
    {
        if (empty($cfg->odbc_dsn)) {
            throw new \RuntimeException(
                'No se ha configurado el DSN ODBC en Configuración > SQL Server CI.'
            );
        }

        if (! extension_loaded('pdo_odbc')) {
            throw new \RuntimeException(
                'La extensión pdo_odbc no está cargada en PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ').'
            );
        }

        if (empty($cfg->username)) {
            throw new \RuntimeException(
                'El campo Usuario está vacío. Complétalo en Configuración → SQL Server CI.'
            );
        }

        $pdo = new \PDO(
            'odbc:' . $cfg->odbc_dsn,
            $cfg->username,
            $cfg->password,
            [
                \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_PERSISTENT => false,
            ]
        );

        return new SqlServerConnection(
            $pdo,
            $cfg->database_name ?? '',
            '',
            [
                'driver'   => 'sqlsrv',
                'database' => $cfg->database_name ?? '',
                'prefix'   => '',
            ]
        );
    }
}
