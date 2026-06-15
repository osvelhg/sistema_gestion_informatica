<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Entity;
use App\Models\ExternalEntityDbSetting;
use App\Models\User;
use App\Support\UserEntityAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

class ExternalEntityDbService
{
    public const DYNAMIC_CONNECTION_NAME = 'external_entity_db_dynamic';

    private const CONNECTION_NAME = self::DYNAMIC_CONNECTION_NAME;

    public static function purgeDynamicConnection(): void
    {
        DB::purge(self::CONNECTION_NAME);
    }
    private const MAX_PREVIEW_RECORDS = 500;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers de conexión
    // ──────────────────────────────────────────────────────────────────────────

    private static function dbName(ExternalEntityDbSetting $cfg, string $entityCode): string
    {
        $padding = (int) ($cfg->code_padding ?? 0);

        if ($padding > 0) {
            // Normaliza quitando ceros iniciales y luego rellena hasta el ancho configurado.
            // Así "819" y "00819" producen el mismo resultado con padding=5: "00819".
            $numericPart = ltrim($entityCode, '0') ?: '0';
            $code        = str_pad($numericPart, $padding, '0', STR_PAD_LEFT);
        } else {
            // Sin padding: usa el código tal como está en la base de datos local.
            $code = $entityCode;
        }

        return $cfg->db_prefix . $code;
    }

    private static function bootConnection(ExternalEntityDbSetting $cfg, string $entityCode): void
    {
        $driver = $cfg->driver ?: 'pgsql';
        $dbName = static::dbName($cfg, $entityCode);

        $base = [
            'driver'   => $driver,
            'host'     => $cfg->host,
            'port'     => $cfg->port ?: static::defaultPort($driver),
            'database' => $dbName,
            'username' => $cfg->username,
            'password' => $cfg->password ?? '',
            'prefix'   => '',
        ];

        $extras = match ($driver) {
            'pgsql'  => ['charset' => 'utf8', 'sslmode' => 'prefer', 'schema' => 'public'],
            'sqlsrv' => [],
            default  => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => false],
        };

        Config::set('database.connections.' . self::CONNECTION_NAME, array_merge($base, $extras));
        DB::purge(self::CONNECTION_NAME);
        DB::reconnect(self::CONNECTION_NAME);
        DB::connection(self::CONNECTION_NAME)->getPdo()->setAttribute(
            PDO::ATTR_TIMEOUT,
            $cfg->timeout ?: 5
        );
    }

    private static function defaultPort(string $driver): int
    {
        return match ($driver) {
            'pgsql'  => 5432,
            'sqlsrv' => 1433,
            default  => 3306,
        };
    }

    private static function wrapIdent(string $ident, string $driver = 'pgsql'): string
    {
        return match ($driver) {
            'pgsql'  => '"' . str_replace('"', '""', $ident) . '"',
            'sqlsrv' => '[' . str_replace(']', ']]', $ident) . ']',
            default  => '`' . str_replace('`', '``', $ident) . '`',
        };
    }

    /**
     * Tabla opcionalmente calificada con esquema (esquema.tabla). Usado en FROM para PostgreSQL/SQL Server/MySQL.
     */
    private static function wrapTableIdent(string $name, string $driver = 'pgsql'): string
    {
        $name = trim($name);
        if ($name === '' || ! str_contains($name, '.')) {
            return static::wrapIdent($name, $driver);
        }

        $parts = explode('.', $name, 2);
        $schema = trim($parts[0] ?? '');
        $table  = trim($parts[1] ?? '');
        if ($schema === '' || $table === '') {
            return static::wrapIdent($name, $driver);
        }

        return static::wrapIdent($schema, $driver).'.'.static::wrapIdent($table, $driver);
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

    /**
     * Garantiza que un string sea UTF-8 válido.
     * Las BDs con charset latin1/cp1252 devuelven bytes que no son UTF-8 válidos.
     */
    private static function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        return mb_check_encoding($converted, 'UTF-8') ? $converted : mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API pública
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Conecta al servidor sin especificar una BD de entidad (usa BD de sistema según driver).
     */
    private static function bootServerConnection(ExternalEntityDbSetting $cfg): void
    {
        $driver = $cfg->driver ?: 'pgsql';

        $systemDb = match ($driver) {
            'pgsql'  => 'postgres',
            'sqlsrv' => 'master',
            default  => 'information_schema',
        };

        $base = [
            'driver'   => $driver,
            'host'     => $cfg->host,
            'port'     => $cfg->port ?: static::defaultPort($driver),
            'database' => $systemDb,
            'username' => $cfg->username,
            'password' => $cfg->password ?? '',
            'prefix'   => '',
        ];

        $extras = match ($driver) {
            'pgsql'  => ['charset' => 'utf8', 'sslmode' => 'prefer', 'schema' => 'public'],
            'sqlsrv' => [],
            default  => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => false],
        };

        Config::set('database.connections.' . self::CONNECTION_NAME, array_merge($base, $extras));
        DB::purge(self::CONNECTION_NAME);
        DB::reconnect(self::CONNECTION_NAME);
        DB::connection(self::CONNECTION_NAME)->getPdo()->setAttribute(
            PDO::ATTR_TIMEOUT,
            $cfg->timeout ?: 5
        );
    }

    /**
     * Lista las bases de datos del servidor que coincidan con el prefijo configurado.
     *
     * @return array{success: bool, databases?: list<string>, message?: string}
     */
    public static function listDatabases(?ExternalEntityDbSetting $cfg = null): array
    {
        $cfg ??= ExternalEntityDbSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado.'];
        }

        try {
            static::bootServerConnection($cfg);

            $driver = $cfg->driver ?: 'pgsql';
            $prefix = $cfg->db_prefix ?? '';

            $databases = match ($driver) {
                'pgsql'  => static::listDatabasesPgsql($prefix),
                'sqlsrv' => static::listDatabasesSqlsrv($prefix),
                default  => static::listDatabasesMysql($prefix),
            };

            sort($databases);

            return ['success' => true, 'databases' => $databases];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => static::toUtf8($e->getMessage())];
        } finally {
            DB::purge(self::CONNECTION_NAME);
        }
    }

    private static function listDatabasesPgsql(string $prefix): array
    {
        $pdo  = DB::connection(self::CONNECTION_NAME)->getPdo();
        $stmt = $pdo->prepare(
            "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE ? ORDER BY datname"
        );
        $stmt->execute([$prefix . '%']);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'datname');
    }

    private static function listDatabasesMysql(string $prefix): array
    {
        $pdo = DB::connection(self::CONNECTION_NAME)->getPdo();
        // En MySQL/MariaDB cada "base de datos" es un schema; excluir catálogos del sistema.
        $like = ($prefix === '' || $prefix === null) ? '%' : $prefix.'%';
        $stmt = $pdo->prepare(
            'SELECT schema_name FROM information_schema.schemata '
            ."WHERE schema_name NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys') "
            .'AND schema_name LIKE ? ORDER BY schema_name'
        );
        $stmt->execute([$like]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'schema_name');
    }

    private static function listDatabasesSqlsrv(string $prefix): array
    {
        $pdo  = DB::connection(self::CONNECTION_NAME)->getPdo();
        $stmt = $pdo->prepare(
            "SELECT name FROM sys.databases WHERE name LIKE ? ORDER BY name"
        );
        $stmt->execute([$prefix . '%']);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
    }

    /**
     * Lista las tablas de una base de datos específica del servidor externo.
     *
     * @return array{success: bool, tables?: list<string>, message?: string}
     */
    public static function listTables(?ExternalEntityDbSetting $cfg = null, string $dbName = ''): array
    {
        $cfg ??= ExternalEntityDbSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado.'];
        }
        if ($dbName === '') {
            return ['success' => false, 'message' => 'Debes indicar el nombre de la base de datos.'];
        }

        try {
            $driver = $cfg->driver ?: 'pgsql';

            // Conectar directamente a la BD indicada
            $base = [
                'driver'   => $driver,
                'host'     => $cfg->host,
                'port'     => $cfg->port ?: static::defaultPort($driver),
                'database' => $dbName,
                'username' => $cfg->username,
                'password' => $cfg->password ?? '',
                'prefix'   => '',
            ];

            $extras = match ($driver) {
                'pgsql'  => ['charset' => 'utf8', 'sslmode' => 'prefer', 'schema' => 'public'],
                'sqlsrv' => [],
                default  => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => false],
            };

            Config::set('database.connections.' . self::CONNECTION_NAME, array_merge($base, $extras));
            DB::purge(self::CONNECTION_NAME);
            DB::reconnect(self::CONNECTION_NAME);
            DB::connection(self::CONNECTION_NAME)->getPdo()->setAttribute(
                PDO::ATTR_TIMEOUT,
                $cfg->timeout ?: 5
            );

            $tables = match ($driver) {
                'pgsql'  => static::listTablesPgsql(),
                'sqlsrv' => static::listTablesSqlsrv(),
                default  => static::listTablesMysql($dbName),
            };

            sort($tables);

            $listingNote = match ($driver) {
                'pgsql' => 'PostgreSQL: se listan tablas de todos los esquemas de usuario (no solo public). Fuera de public el nombre se muestra como esquema.tabla; así puedes enlazar nomina.activos, rh.tabla, etc.',
                'sqlsrv' => 'SQL Server: tablas de la base de datos actual (TABLE_CATALOG = DB_NAME()). Fuera de dbo se muestra esquema.tabla.',
                default => 'MySQL/MariaDB: consulta information_schema.tables para table_schema = base seleccionada (solo BASE TABLE).',
            };

            return [
                'success' => true,
                'tables'  => $tables,
                'meta'    => [
                    'driver'       => $driver,
                    'database'     => $dbName,
                    'listing_note' => $listingNote,
                    'sql_tables'   => match ($driver) {
                        'pgsql' => "SELECT schemaname, tablename FROM pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema') …",
                        'sqlsrv' => 'SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE=\'BASE TABLE\' AND TABLE_CATALOG = DB_NAME()',
                        default => 'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = \'BASE TABLE\'',
                    },
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => static::toUtf8($e->getMessage())];
        } finally {
            DB::purge(self::CONNECTION_NAME);
        }
    }

    private static function listTablesPgsql(): array
    {
        $pdo = DB::connection(self::CONNECTION_NAME)->getPdo();
        $stmt = $pdo->query(
            "SELECT schemaname, tablename FROM pg_tables "
            ."WHERE schemaname NOT IN ('pg_catalog', 'information_schema') "
            ."AND schemaname NOT LIKE 'pg\\_temp%' ESCAPE '\\' "
            .'ORDER BY schemaname, tablename'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out  = [];
        foreach ($rows as $row) {
            $schema = $row['schemaname'] ?? '';
            $table  = $row['tablename'] ?? '';
            if ($table === '') {
                continue;
            }
            // En public el nombre corto basta; en otros esquemas: esquema.tabla (para configurar y SQL calificado).
            $out[] = $schema === 'public' ? $table : $schema.'.'.$table;
        }

        return $out;
    }

    private static function listTablesMysql(string $dbName): array
    {
        $pdo  = DB::connection(self::CONNECTION_NAME)->getPdo();
        $stmt = $pdo->prepare(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY table_name"
        );
        $stmt->execute([$dbName]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'table_name');
    }

    private static function listTablesSqlsrv(): array
    {
        $pdo = DB::connection(self::CONNECTION_NAME)->getPdo();
        $stmt = $pdo->query(
            "SELECT TABLE_SCHEMA AS s, TABLE_NAME AS t FROM INFORMATION_SCHEMA.TABLES "
            ."WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_CATALOG = DB_NAME() "
            .'ORDER BY TABLE_SCHEMA, TABLE_NAME'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out  = [];
        foreach ($rows as $row) {
            $schema = $row['s'] ?? '';
            $table  = $row['t'] ?? '';
            if ($table === '') {
                continue;
            }
            $schemaLower = strtolower((string) $schema);
            $out[]       = ($schemaLower === 'dbo') ? $table : $schema.'.'.$table;
        }

        return $out;
    }

    /**
     * Prueba la conexión a la BD de una entidad específica.
     */
    public static function testConnection(?ExternalEntityDbSetting $cfg = null, string $entityCode = ''): array
    {
        $cfg ??= ExternalEntityDbSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado.'];
        }
        if ($entityCode === '') {
            return ['success' => false, 'message' => 'Debes indicar un código de entidad para probar.'];
        }

        try {
            static::bootConnection($cfg, $entityCode);
            $dbName = static::dbName($cfg, $entityCode);
            DB::connection(self::CONNECTION_NAME)->select('SELECT 1');
            return ['success' => true, 'message' => "Conexión exitosa a la base de datos \"{$dbName}\"."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => static::toUtf8($e->getMessage())];
        } finally {
            DB::purge(self::CONNECTION_NAME);
        }
    }

    /**
     * Vista previa (sin escribir) de los departamentos a importar.
     * Cruza contra areas_responsabilidad para obtener código + nombre,
     * luego compara contra departments local por (codigo_entidad, codigo_area).
     *
     * @return array{success: bool, message?: string, records?: array, errors?: array}
     */
    /**
     * @param  int[]|null  $entityIds  IDs de entidades a procesar; null = todas las activas
     */
    public static function previewDepartments(?ExternalEntityDbSetting $cfg = null, ?array $entityIds = null): array
    {
        $cfg ??= ExternalEntityDbSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado. Guarda la configuración primero.'];
        }

        $query = Entity::active();
        if (! empty($entityIds)) {
            $query->whereIn('id', $entityIds);
        }
        $entities = $query->get(['id', 'name', 'code']);

        if ($entities->isEmpty()) {
            return ['success' => false, 'message' => 'No hay entidades activas para procesar.'];
        }

        $records = [];
        $errors  = [];

        foreach ($entities as $entity) {
            try {
                $entityRecords = static::fetchAreasForEntity($cfg, $entity, dryRun: true);
                if (! empty($entityRecords)) {
                    $records[$entity->code] = [
                        'entity_id'   => $entity->id,
                        'entity_name' => $entity->name,
                        'entity_code' => $entity->code,
                        'departments' => $entityRecords,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[$entity->code] = static::toUtf8($e->getMessage());
            } finally {
                DB::purge(self::CONNECTION_NAME);
            }
        }

        return [
            'success' => true,
            'records' => $records,
            'errors'  => $errors,
        ];
    }

    /**
     * Sincroniza departamentos (aplica todo, sin selección individual de cambios).
     *
     * @param  int[]|null  $entityIds  IDs de entidades a procesar; null = todas las activas
     */
    public static function syncDepartments(?ExternalEntityDbSetting $cfg = null, ?array $entityIds = null): array
    {
        $cfg ??= ExternalEntityDbSetting::current();

        if (! $cfg->host) {
            return ['success' => false, 'message' => 'Host no configurado.'];
        }

        $query = Entity::active();
        if (! empty($entityIds)) {
            $query->whereIn('id', $entityIds);
        }
        $entities = $query->get(['id', 'name', 'code']);
        $summary  = ['created' => 0, 'reactivated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($entities as $entity) {
            try {
                $result = static::fetchAreasForEntity($cfg, $entity, dryRun: false);
                $summary['created']     += $result['created'] ?? 0;
                $summary['reactivated'] += $result['reactivated'] ?? 0;
                $summary['skipped']     += $result['skipped'] ?? 0;
            } catch (\Throwable $e) {
                $summary['errors'][$entity->code] = static::toUtf8($e->getMessage());
            } finally {
                DB::purge(self::CONNECTION_NAME);
            }
        }

        $cfg->update([
            'last_synced_at'    => Carbon::now(),
            'last_sync_summary' => array_merge($summary, ['ok' => true]),
        ]);

        return ['success' => true, 'summary' => $summary];
    }

    /**
     * Aplica exactamente los cambios seleccionados por el usuario desde la vista previa.
     *
     * @param  list<array{action: string, entity_id: int, entity_code: string, codigo_area: string, name: string, local_id?: int}>  $changes
     */
    public static function applySelectedDepartmentChanges(array $changes): array
    {
        $created     = 0;
        $reactivated = 0;

        DB::transaction(static function () use ($changes, &$created, &$reactivated): void {
            foreach ($changes as $item) {
                $action      = $item['action'] ?? '';
                $entityId    = (int) ($item['entity_id'] ?? 0);
                $entityCode  = $item['entity_code'] ?? '';
                $codigoArea  = $item['codigo_area'] ?? '';
                $name        = $item['name'] ?? '';

                if ($action === 'create' && $entityId && $codigoArea !== '' && $name !== '') {
                    $code = static::generateDeptCode($codigoArea, $entityId);
                    Department::create([
                        'entity_id'      => $entityId,
                        'name'           => $name,
                        'code'           => $code,
                        'codigo_area'    => $codigoArea,
                        'codigo_entidad' => $entityCode,
                        'active'         => true,
                    ]);
                    $created++;
                } elseif ($action === 'reactivate' && ! empty($item['local_id'])) {
                    $dept = Department::find($item['local_id']);
                    if ($dept) {
                        $dept->update([
                            'active'         => true,
                            'name'           => $name ?: $dept->name,
                            'codigo_area'    => $codigoArea ?: $dept->codigo_area,
                            'codigo_entidad' => $entityCode ?: $dept->codigo_entidad,
                        ]);
                        $reactivated++;
                    }
                } elseif ($action === 'update_name' && ! empty($item['local_id'])) {
                    $dept = Department::find($item['local_id']);
                    if ($dept) {
                        $dept->update(['name' => $name]);
                        $reactivated++; // contamos como "actualizado"
                    }
                }
            }
        });

        $cfg = ExternalEntityDbSetting::current();
        $cfg->update([
            'last_synced_at'    => Carbon::now(),
            'last_sync_summary' => [
                'ok'          => true,
                'mode'        => 'selected',
                'created'     => $created,
                'reactivated' => $reactivated,
                'at'          => Carbon::now()->toIso8601String(),
            ],
        ]);

        return [
            'success'     => true,
            'created'     => $created,
            'reactivated' => $reactivated,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Consulta la BD de una entidad y devuelve los cambios de departamentos.
     *
     * Lógica:
     *   1. Obtiene los códigos de área presentes en `activos` (filtrado por grupo/subgrupo).
     *   2. Enriquece con nombre desde `areas_responsabilidad`.
     *   3. Compara contra `departments` local usando (entity_id, codigo_area).
     *   4. Determina acción: create / reactivate / update_name / skip.
     *
     * En dry-run devuelve lista de registros; en live aplica y devuelve contadores.
     */
    private static function fetchAreasForEntity(
        ExternalEntityDbSetting $cfg,
        Entity $entity,
        bool $dryRun
    ): array {
        static::bootConnection($cfg, $entity->code);

        $driver  = $cfg->driver ?: 'pgsql';

        // ── Paso 1: obtener códigos de área desde activos ────────────────────
        $activos      = static::wrapTableIdent((string) $cfg->table_name, $driver);
        $areaCol      = static::wrapIdent($cfg->area_column, $driver);      // area_responsabilidad
        $grupoCol     = static::wrapIdent($cfg->grupo_column, $driver);
        $subgrupoCol  = static::wrapIdent($cfg->subgrupo_column, $driver);

        $sqlCodigos = "SELECT DISTINCT {$areaCol} AS __cod "
            . "FROM {$activos} "
            . "WHERE {$grupoCol} = ? AND {$subgrupoCol} = ?";

        $codigos = [];
        foreach (static::iterateRows($sqlCodigos, [$cfg->grupo_value, $cfg->subgrupo_value]) as $row) {
            $cod = trim(static::toUtf8((string) ($row->__cod ?? '')));
            if ($cod !== '') {
                $codigos[] = $cod;
            }
        }

        if (empty($codigos)) {
            return $dryRun ? [] : ['created' => 0, 'reactivated' => 0, 'skipped' => 0];
        }

        // ── Paso 2: enriquecer con nombre desde areas_responsabilidad ────────
        $areasTable   = static::wrapTableIdent((string) $cfg->areas_table, $driver);
        $areaCodCol   = static::wrapIdent($cfg->area_code_column, $driver);  // codigo
        $areaNomCol   = static::wrapIdent($cfg->area_name_column, $driver);  // nombre

        // Placeholders para IN (?)
        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $sqlAreas = "SELECT {$areaCodCol} AS __cod, {$areaNomCol} AS __nom "
            . "FROM {$areasTable} "
            . "WHERE {$areaCodCol} IN ({$placeholders})";

        $areasExternas = [];
        foreach (static::iterateRows($sqlAreas, $codigos) as $row) {
            $cod  = trim(static::toUtf8((string) ($row->__cod ?? '')));
            $nom  = trim(static::toUtf8((string) ($row->__nom ?? '')));
            if ($cod !== '') {
                $areasExternas[$cod] = $nom !== '' ? $nom : $cod;
            }
        }

        // Si la tabla areas_responsabilidad no existe o no devuelve datos,
        // usamos el código como nombre de fallback.
        foreach ($codigos as $cod) {
            if (! isset($areasExternas[$cod])) {
                $areasExternas[$cod] = $cod;
            }
        }

        // ── Paso 3: comparar contra departamentos locales ────────────────────
        $localDepts = Department::where('entity_id', $entity->id)
            ->whereIn('codigo_area', array_keys($areasExternas))
            ->get(['id', 'name', 'codigo_area', 'active'])
            ->keyBy('codigo_area');

        if ($dryRun) {
            $records = [];
            foreach ($areasExternas as $codigoArea => $nombre) {
                if (count($records) >= self::MAX_PREVIEW_RECORDS) {
                    break;
                }
                $local = $localDepts->get($codigoArea);

                if (! $local) {
                    $records[] = [
                        'action'         => 'create',
                        'entity_id'      => $entity->id,
                        'entity_code'    => $entity->code,
                        'codigo_area'    => $codigoArea,
                        'name'           => $nombre,
                    ];
                } elseif (! $local->active) {
                    $records[] = [
                        'action'         => 'reactivate',
                        'entity_id'      => $entity->id,
                        'entity_code'    => $entity->code,
                        'codigo_area'    => $codigoArea,
                        'name'           => $nombre,
                        'local_id'       => $local->id,
                        'current_name'   => $local->name,
                    ];
                } elseif ($local->name !== $nombre) {
                    // Nombre cambió en el servidor externo
                    $records[] = [
                        'action'         => 'update_name',
                        'entity_id'      => $entity->id,
                        'entity_code'    => $entity->code,
                        'codigo_area'    => $codigoArea,
                        'name'           => $nombre,
                        'local_id'       => $local->id,
                        'current_name'   => $local->name,
                    ];
                }
                // active + sin cambios → skip (no se agrega)
            }
            return $records;
        }

        // Live sync
        $created     = 0;
        $reactivated = 0;
        $skipped     = 0;

        foreach ($areasExternas as $codigoArea => $nombre) {
            $local = $localDepts->get($codigoArea);

            if (! $local) {
                $code = static::generateDeptCode($codigoArea, $entity->id);
                Department::create([
                    'entity_id'      => $entity->id,
                    'name'           => $nombre,
                    'code'           => $code,
                    'codigo_area'    => $codigoArea,
                    'codigo_entidad' => $entity->code,
                    'active'         => true,
                ]);
                $created++;
            } elseif (! $local->active) {
                $local->update([
                    'active'         => true,
                    'name'           => $nombre,
                    'codigo_entidad' => $entity->code,
                ]);
                $reactivated++;
            } elseif ($local->name !== $nombre) {
                $local->update(['name' => $nombre]);
                $skipped++; // contamos la actualización de nombre como "procesado"
            } else {
                $skipped++;
            }
        }

        return compact('created', 'reactivated', 'skipped');
    }

    /**
     * Genera un código de departamento único dentro de la entidad, basado en el código de área.
     */
    private static function generateDeptCode(string $codigoArea, int $entityId): string
    {
        // El código de área externo ya es corto y alfanumérico; lo usamos directamente
        $base = mb_strtoupper(trim($codigoArea));
        if ($base === '' || mb_strlen($base) > 20) {
            $base = mb_strtoupper(mb_substr(Str::slug($codigoArea, '_'), 0, 18));
        }
        if ($base === '') {
            $base = 'DEPT';
        }

        $candidate = $base;
        $suffix    = 1;
        while (Department::where('entity_id', $entityId)->where('code', $candidate)->exists()) {
            $candidate = mb_substr($base, 0, 16) . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Busca un activo por número de inventario en todas las entidades activas (bases r4_XXX).
     *
     * @return array{success: bool, found: bool, entity_id?: int, entity_name?: string, department_id?: int|null, department_name?: string|null, codigo_area?: string, message?: string}
     */
    public static function lookupInventoryAcrossEntities(string $inventoryNumber, ?ExternalEntityDbSetting $cfg = null, ?User $forUser = null): array
    {
        $cfg ??= ExternalEntityDbSetting::current();

        if (! $cfg->enabled || ! $cfg->host || trim($inventoryNumber) === '') {
            return [
                'success' => false,
                'found'   => false,
                'message' => 'La consulta a RODAS no está activa o falta el número de inventario.',
            ];
        }

        $inv = trim($inventoryNumber);
        $entityQuery = Entity::active()->orderBy('name');
        if ($forUser) {
            UserEntityAccess::applyToEntitiesQuery($entityQuery, $forUser);
        }
        $entities = $entityQuery->get(['id', 'name', 'code']);

        foreach ($entities as $entity) {
            try {
                $res = static::lookupActivoForEntity($cfg, $entity, $inv);
                if ($res['found']) {
                    return [
                        'success'          => true,
                        'found'            => true,
                        'entity_id'        => $entity->id,
                        'entity_name'      => $entity->name,
                        'department_id'    => $res['department_id'],
                        'department_name'  => $res['department_name'],
                        'codigo_area'      => $res['codigo_area'],
                    ];
                }
            } catch (\Throwable) {
                // Siguiente entidad si esta BD no existe o falla la consulta.
            } finally {
                DB::purge(self::CONNECTION_NAME);
            }
        }

        return [
            'success' => true,
            'found'   => false,
            'message' => 'No se encontró el inventario en RODAS (servidor contable) para ninguna entidad.',
        ];
    }

    /**
     * Consulta la fila de activos para una entidad concreta (mismos filtros grupo/subgrupo que la sincronización de departamentos).
     *
     * @return array{found: bool, codigo_area?: string, department_id?: int|null, department_name?: string|null}
     */
    public static function lookupActivoForEntity(
        ExternalEntityDbSetting $cfg,
        Entity $entity,
        string $inventoryNumber
    ): array {
        static::bootConnection($cfg, $entity->code);

        $driver  = $cfg->driver ?: 'pgsql';
        $activos = static::wrapTableIdent((string) $cfg->table_name, $driver);
        $invCol  = static::wrapIdent((string) ($cfg->inventory_lookup_column ?: 'codigo'), $driver);
        $areaCol = static::wrapIdent($cfg->area_column, $driver);
        $grupoCol = static::wrapIdent($cfg->grupo_column, $driver);
        $subgrupoCol = static::wrapIdent($cfg->subgrupo_column, $driver);

        $sql = "SELECT {$areaCol} AS __area FROM {$activos} "
            ."WHERE {$invCol} = ? AND {$grupoCol} = ? AND {$subgrupoCol} = ? LIMIT 1";

        $row = null;
        foreach (static::iterateRows($sql, [$inventoryNumber, $cfg->grupo_value, $cfg->subgrupo_value]) as $r) {
            $row = $r;
            break;
        }

        if (! $row) {
            return ['found' => false];
        }

        $codigoArea = trim(static::toUtf8((string) ($row->__area ?? '')));

        $dept = Department::where('entity_id', $entity->id)
            ->get(['id', 'name', 'codigo_area'])
            ->first(function ($d) use ($codigoArea) {
                return trim((string) $d->codigo_area) === $codigoArea;
            });

        return [
            'found'            => true,
            'codigo_area'      => $codigoArea,
            'department_id'    => $dept?->id,
            'department_name'  => $dept?->name,
        ];
    }
}
