<?php

namespace App\Services;

use App\Models\AreaVenta;
use App\Models\ExternalAlmacenesSetting;
use App\Models\SalesFloor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;

class ExternalAlmacenesService
{
    public const DYNAMIC_CONNECTION_NAME = 'external_almacenes_dynamic';

    private const CONNECTION_NAME = self::DYNAMIC_CONNECTION_NAME;

    private const MAX_RAW_RECORDS = 300;

    // Todos los flags de tipo que puede manejar la tabla Almacenes
    private const ALL_TIPO_FLAGS = [
        'MercanciaVenta',
        'Exhibicion',
        'Interno',
        'Gastronomia',
        'Insumo',
        'Inversiones',
        'Boutique',
        'Consignacion',
        'Emergente',
        'Distribuir',
        'Merma',
        'MermaOrigen',
        'ReservaDiv',
        'ReservaNac',
        'DespachoDiv',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers de conexión
    // ──────────────────────────────────────────────────────────────────────────

    private static function bootConnection(ExternalAlmacenesSetting $cfg): void
    {
        Config::set('database.connections.' . self::CONNECTION_NAME, [
            'driver'   => 'sqlsrv',
            'host'     => $cfg->host,
            'port'     => $cfg->port ?: 1433,
            'database' => $cfg->database_name,
            'username' => $cfg->username,
            'password' => $cfg->password ?? '',
            'prefix'   => '',
        ]);
        DB::purge(self::CONNECTION_NAME);
        DB::reconnect(self::CONNECTION_NAME);
        DB::connection(self::CONNECTION_NAME)->getPdo()->setAttribute(
            PDO::ATTR_TIMEOUT,
            $cfg->timeout ?: 10
        );
    }

    private static function wrapIdent(string $ident): string
    {
        return '[' . str_replace(']', ']]', $ident) . ']';
    }

    private static function tableRef(ExternalAlmacenesSetting $cfg): string
    {
        $schema = trim($cfg->schema_name ?: 'dbo');
        $table  = trim($cfg->table_name ?: 'Almacenes');
        return static::wrapIdent($schema) . '.' . static::wrapIdent($table);
    }

    /** @return \Generator<object> */
    private static function iterateRows(string $sql, array $bindings = []): \Generator
    {
        $pdo  = DB::connection(self::CONNECTION_NAME)->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $stmt->setFetchMode(PDO::FETCH_OBJ);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    private static function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        return mb_check_encoding($converted, 'UTF-8')
            ? $converted
            : mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    public static function purgeDynamicConnection(): void
    {
        DB::purge(self::CONNECTION_NAME);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers de filtrado y normalización
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Construye la cláusula WHERE según los filtros configurados.
     * Devuelve [string $whereClause, array $bindings].
     */
    private static function buildFilterWhere(ExternalAlmacenesSetting $cfg, ?int $idUnidad = null): array
    {
        $conditions = [];
        $bindings   = [];

        if ($idUnidad !== null) {
            $col          = static::wrapIdent($cfg->id_unidad_column ?: 'IdUnidad');
            $conditions[] = "{$col} = ?";
            $bindings[]   = $idUnidad;
        }

        if ($cfg->import_solo_abierto) {
            $conditions[] = '[Abierto] = 1';
        }

        $tipos = $cfg->import_tipos ?? [];
        if (! empty($tipos)) {
            // Validar que los flags existan en el listado permitido (protección contra inyección)
            $validFlags = array_intersect($tipos, self::ALL_TIPO_FLAGS);
            if (! empty($validFlags)) {
                $tipoClauses = array_map(
                    fn (string $flag) => static::wrapIdent($flag) . ' = 1',
                    $validFlags
                );
                $conditions[] = '(' . implode(' OR ', $tipoClauses) . ')';
            }
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $bindings];
    }

    /**
     * Normaliza un nombre de almacén para comparación fuzzy.
     * Convierte a mayúsculas y elimina puntuación/espacios extra.
     */
    public static function normalizeAreaName(string $name): string
    {
        $name = mb_strtoupper(trim($name));
        // Reemplazar puntuación común por espacio
        $name = str_replace(['.', ',', '-', '_', '(', ')', '/', '\\', '"', "'"], ' ', $name);
        // Colapsar espacios múltiples
        return preg_replace('/\s+/', ' ', $name) ?? $name;
    }

    /**
     * Determina el tipo del almacén revisando los flags en orden de prioridad.
     */
    private static function deriveTipo(object $row): ?string
    {
        $flags = [
            'MercanciaVenta',
            'Exhibicion',
            'Gastronomia',
            'Boutique',
            'Insumo',
            'Inversiones',
            'Consignacion',
            'Emergente',
            'Interno',
            'Distribuir',
            'Merma',
            'MermaOrigen',
            'ReservaDiv',
            'ReservaNac',
            'DespachoDiv',
        ];
        foreach ($flags as $flag) {
            if (! empty($row->{$flag})) {
                return $flag;
            }
        }
        return null;
    }

    /**
     * Resuelve el SalesFloor local que corresponde a un IdUnidad de Almacenes.
     * Estrategia: 1) datacell_unit_id exacto, 2) codigo_golden.
     */
    private static function resolveSalesFloor(int $idUnidad, ?int $overrideSalesFloorId = null): ?SalesFloor
    {
        if ($overrideSalesFloorId) {
            return SalesFloor::find($overrideSalesFloorId);
        }
        return SalesFloor::where('datacell_unit_id', (string) $idUnidad)->first()
            ?? SalesFloor::where('codigo_golden', (string) $idUnidad)->first();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API pública
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Prueba la conexión al SQL Server configurado.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public static function testConnection(?ExternalAlmacenesSetting $cfg = null): array
    {
        $cfg ??= ExternalAlmacenesSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado.'];
        }

        try {
            static::bootConnection($cfg);
            DB::connection(self::CONNECTION_NAME)->select('SELECT 1');

            $tableRef = static::tableRef($cfg);
            $count = DB::connection(self::CONNECTION_NAME)
                ->selectOne("SELECT COUNT(*) AS total FROM {$tableRef}");

            return [
                'success' => true,
                'message' => "Conexión exitosa. La tabla contiene {$count->total} registros.",
                'count'   => (int) $count->total,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => static::toUtf8($e->getMessage())];
        } finally {
            DB::purge(self::CONNECTION_NAME);
        }
    }

    /**
     * Devuelve hasta MAX_RAW_RECORDS filas de la tabla Almacenes con los filtros configurados.
     * Útil para inspeccionar los datos antes de sincronizar.
     *
     * @return array{success: bool, records?: list<array>, message?: string}
     */
    public static function fetchRaw(
        ?ExternalAlmacenesSetting $cfg = null,
        ?int $idUnidad = null,
        int $limit = self::MAX_RAW_RECORDS
    ): array {
        $cfg ??= ExternalAlmacenesSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado.'];
        }

        try {
            static::bootConnection($cfg);

            $tableRef   = static::tableRef($cfg);
            $pkCol      = static::wrapIdent($cfg->id_almacen_pk_column ?: 'IdGerenciaIdAlmacen');
            $unidadCol  = static::wrapIdent($cfg->id_unidad_column ?: 'IdUnidad');
            $almacenCol = static::wrapIdent($cfg->almacen_column ?: 'Almacen');
            $pisoCol    = static::wrapIdent($cfg->id_piso_column ?: 'IdPiso');

            [$where, $bindings] = static::buildFilterWhere($cfg, $idUnidad);

            // Seleccionar columnas principales + todos los flags de tipo
            $flagCols = implode(', ', array_map(
                fn (string $f) => static::wrapIdent($f),
                self::ALL_TIPO_FLAGS
            ));

            $sql = "SELECT TOP {$limit} "
                . "{$pkCol} AS almacen_id, "
                . "{$unidadCol} AS id_unidad, "
                . "{$almacenCol} AS almacen, "
                . "{$pisoCol} AS id_piso, "
                . "[Abierto], [MLC], {$flagCols} "
                . "FROM {$tableRef} "
                . "{$where} "
                . "ORDER BY {$unidadCol}, {$almacenCol}";

            $records = [];
            foreach (static::iterateRows($sql, $bindings) as $row) {
                $records[] = [
                    'almacen_id'   => $row->almacen_id,
                    'id_unidad'    => $row->id_unidad,
                    'almacen'      => static::toUtf8((string) ($row->almacen ?? '')),
                    'id_piso'      => $row->id_piso,
                    'abierto'      => (bool) ($row->Abierto ?? false),
                    'mlc'          => (bool) ($row->MLC ?? false),
                    'tipo'         => static::deriveTipo($row),
                ];
            }

            return ['success' => true, 'records' => $records];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => static::toUtf8($e->getMessage())];
        } finally {
            DB::purge(self::CONNECTION_NAME);
        }
    }

    /**
     * Genera un preview de sincronización para un SalesFloor dado.
     * Compara los almacenes remotos (filtrados) contra las AreaVenta locales.
     *
     * @param  int       $salesFloorId         ID del SalesFloor local
     * @param  int|null  $overrideIdUnidad      IdUnidad a usar en lugar del datacell_unit_id del piso
     * @return array{success: bool, sales_floor_id?: int, id_unidad?: int, items?: list<array>, message?: string}
     */
    public static function buildSyncPreview(
        int $salesFloorId,
        ?int $overrideIdUnidad = null,
        ?ExternalAlmacenesSetting $cfg = null
    ): array {
        $cfg ??= ExternalAlmacenesSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado.'];
        }

        $floor = SalesFloor::with('areasVenta')->find($salesFloorId);
        if (! $floor) {
            return ['success' => false, 'message' => 'Piso de venta no encontrado.'];
        }

        // Determinar IdUnidad
        $idUnidad = $overrideIdUnidad
            ?? (is_numeric($floor->datacell_unit_id) ? (int) $floor->datacell_unit_id : null)
            ?? (is_numeric($floor->codigo_golden) ? (int) $floor->codigo_golden : null);

        if ($idUnidad === null) {
            return [
                'success' => false,
                'message' => "El piso \"{$floor->name}\" no tiene IdUnidad configurado. "
                    . "Usa el campo 'IdUnidad manual' para especificarlo.",
            ];
        }

        try {
            static::bootConnection($cfg);

            $tableRef   = static::tableRef($cfg);
            $pkCol      = static::wrapIdent($cfg->id_almacen_pk_column ?: 'IdGerenciaIdAlmacen');
            $unidadCol  = static::wrapIdent($cfg->id_unidad_column ?: 'IdUnidad');
            $almacenCol = static::wrapIdent($cfg->almacen_column ?: 'Almacen');
            $pisoCol    = static::wrapIdent($cfg->id_piso_column ?: 'IdPiso');

            [$where, $bindings] = static::buildFilterWhere($cfg, $idUnidad);

            $flagCols = implode(', ', array_map(
                fn (string $f) => static::wrapIdent($f),
                self::ALL_TIPO_FLAGS
            ));

            $sql = "SELECT "
                . "{$pkCol} AS almacen_id, "
                . "{$unidadCol} AS id_unidad, "
                . "{$almacenCol} AS almacen, "
                . "{$pisoCol} AS id_piso, "
                . "[Abierto], [MLC], {$flagCols} "
                . "FROM {$tableRef} "
                . "{$where} "
                . "ORDER BY {$almacenCol}";

            // Índice de áreas locales por nombre normalizado
            $localAreas = $floor->areasVenta->keyBy(
                fn (AreaVenta $a) => static::normalizeAreaName($a->name)
            );

            $items = [];
            foreach (static::iterateRows($sql, $bindings) as $row) {
                $nombreRemoto     = static::toUtf8(trim((string) ($row->almacen ?? '')));
                $nombreNormalizado = static::normalizeAreaName($nombreRemoto);
                $almacenId        = (int) ($row->almacen_id ?? 0);
                $idAlmacenLocal   = $almacenId % 1000; // IdAlmacen calculado
                $tipo             = static::deriveTipo($row);
                $abierto          = (bool) ($row->Abierto ?? false);
                $mlc              = (bool) ($row->MLC ?? false);

                $localArea = $localAreas->get($nombreNormalizado);

                if (! $localArea) {
                    $action = $cfg->sync_creates_areas ? 'create' : 'skip_no_local';
                } else {
                    // Verificar si hay cambios en los campos de Almacenes
                    $hasCambios = $localArea->almacen_id !== $almacenId
                        || $localArea->id_almacen_local !== $idAlmacenLocal
                        || $localArea->almacen_tipo !== $tipo
                        || (bool) $localArea->almacen_abierto !== $abierto
                        || (bool) $localArea->almacen_mlc !== $mlc;
                    $action = $hasCambios ? 'update' : 'skip';
                }

                $items[] = [
                    'action'           => $action,
                    'almacen_nombre'   => $nombreRemoto,
                    'almacen_id'       => $almacenId,
                    'id_almacen_local' => $idAlmacenLocal,
                    'almacen_tipo'     => $tipo,
                    'almacen_abierto'  => $abierto,
                    'almacen_mlc'      => $mlc,
                    'local_area_id'    => $localArea?->id,
                    'local_area_name'  => $localArea?->name,
                    'local_almacen_id' => $localArea?->almacen_id,
                    'local_tipo'       => $localArea?->almacen_tipo,
                ];
            }

            return [
                'success'        => true,
                'sales_floor_id' => $salesFloorId,
                'floor_name'     => $floor->name,
                'id_unidad'      => $idUnidad,
                'items'          => $items,
                'totals'         => [
                    'create'        => count(array_filter($items, fn ($i) => $i['action'] === 'create')),
                    'update'        => count(array_filter($items, fn ($i) => $i['action'] === 'update')),
                    'skip'          => count(array_filter($items, fn ($i) => $i['action'] === 'skip')),
                    'skip_no_local' => count(array_filter($items, fn ($i) => $i['action'] === 'skip_no_local')),
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => static::toUtf8($e->getMessage())];
        } finally {
            DB::purge(self::CONNECTION_NAME);
        }
    }

    /**
     * Aplica la sincronización para los items seleccionados por el usuario desde el preview.
     *
     * Cada item debe tener:
     *   - action: 'create' | 'update'
     *   - sales_floor_id: int
     *   - almacen_nombre: string
     *   - almacen_id: int
     *   - id_almacen_local: int
     *   - almacen_tipo: string|null
     *   - almacen_abierto: bool
     *   - almacen_mlc: bool
     *   - local_area_id: int|null (requerido para 'update')
     *
     * IMPORTANTE: Solo se tocan los 5 campos de Almacenes. Nunca se modifican campos POS/QR.
     *
     * @return array{success: bool, created: int, updated: int, errors: list<string>}
     */
    public static function applySync(
        int $salesFloorId,
        array $selectedItems,
        ?ExternalAlmacenesSetting $cfg = null
    ): array {
        $cfg ??= ExternalAlmacenesSetting::current();

        $created = 0;
        $updated = 0;
        $errors  = [];

        DB::transaction(static function () use ($salesFloorId, $selectedItems, &$created, &$updated, &$errors): void {
            foreach ($selectedItems as $item) {
                $action = $item['action'] ?? '';

                $almacenFields = [
                    'almacen_id'       => (int) ($item['almacen_id'] ?? 0) ?: null,
                    'id_almacen_local' => (int) ($item['id_almacen_local'] ?? 0) ?: null,
                    'almacen_tipo'     => $item['almacen_tipo'] ?? null,
                    'almacen_abierto'  => (bool) ($item['almacen_abierto'] ?? false),
                    'almacen_mlc'      => (bool) ($item['almacen_mlc'] ?? false),
                ];

                try {
                    if ($action === 'create') {
                        $nombre = trim($item['almacen_nombre'] ?? '');
                        if ($nombre === '') {
                            $errors[] = 'Nombre de almacén vacío, se omite.';
                            continue;
                        }
                        AreaVenta::create(array_merge(
                            ['sales_floor_id' => $salesFloorId, 'name' => $nombre],
                            $almacenFields
                        ));
                        $created++;
                    } elseif ($action === 'update' && ! empty($item['local_area_id'])) {
                        AreaVenta::where('id', (int) $item['local_area_id'])
                            ->where('sales_floor_id', $salesFloorId)
                            ->update($almacenFields);
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = static::toUtf8($e->getMessage());
                }
            }
        });

        $cfg->update([
            'last_synced_at'    => Carbon::now(),
            'last_sync_summary' => [
                'ok'           => true,
                'mode'         => 'selected',
                'created'      => $created,
                'updated'      => $updated,
                'errors_count' => count($errors),
                'at'           => Carbon::now()->toIso8601String(),
            ],
        ]);

        return [
            'success' => empty($errors) || ($created + $updated) > 0,
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }
}
