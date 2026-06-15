<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\ExternalEntitiesPgSetting;
use App\Models\ExternalEntitiesPgTableMapping;
use App\Models\Municipio;
use App\Models\Provincia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ExternalEntitiesPgService
{
    private const CONNECTION_NAME = 'external_sync_dynamic';

    /** Máximo de registros por tipo en la vista previa (evita respuestas gigantes). */
    private const MAX_PREVIEW_RECORDS = 1000;

    // ──────────────────────────────────────────────────────────────────────────
    // Conexión y exploración
    // ──────────────────────────────────────────────────────────────────────────

    public static function testConnection(ExternalEntitiesPgSetting $cfg): array
    {
        try {
            static::bootConnection($cfg);
            DB::connection(self::CONNECTION_NAME)->getPdo();

            return ['success' => true, 'message' => 'Conexion exitosa con la base de datos externa.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error de conexion: '.static::utf8Safe($e->getMessage())];
        }
    }

    /** @return list<string> */
    public static function listTables(ExternalEntitiesPgSetting $cfg, ?string $schema = null): array
    {
        static::bootConnection($cfg);
        $schema = static::resolveSchema($cfg, $schema);

        $rows = DB::connection(self::CONNECTION_NAME)->select(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = ? ORDER BY table_name',
            [$schema, 'BASE TABLE']
        );

        return array_values(array_map(static fn ($r) => $r->table_name, $rows));
    }

    /** @return list<array{name: string, data_type: string}> */
    public static function listColumns(ExternalEntitiesPgSetting $cfg, string $table, ?string $schema = null): array
    {
        static::bootConnection($cfg);
        $schema = static::resolveSchema($cfg, $schema);

        $rows = DB::connection(self::CONNECTION_NAME)->select(
            'SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position',
            [$schema, $table]
        );

        return array_values(array_map(static fn ($r) => [
            'name'      => $r->column_name,
            'data_type' => $r->data_type,
        ], $rows));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sincronización principal
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Sincroniza (o simula en dry-run) provincias, municipios y entidades.
     *
     * En dry-run devuelve además `records` con los cambios detallados por tipo.
     *
     * @param  list<int>|null  $onlyMappingIds  Si se indica, solo procesa esos mapeos (por ID de BD).
     */
    public static function syncEntities(?ExternalEntitiesPgSetting $cfg = null, bool $dryRun = false, ?array $onlyMappingIds = null): array
    {
        $cfg ??= ExternalEntitiesPgSetting::current();

        if (! $cfg->enabled && ! $dryRun) {
            static::persistSyncSummary($cfg, [
                'ok'    => false,
                'error' => 'La sincronizacion externa de entidades esta deshabilitada.',
                'phase' => 'config',
            ]);

            return ['success' => false, 'message' => 'La sincronizacion externa de entidades esta deshabilitada.'];
        }

        try {
            static::bootConnection($cfg);

            $mappingsQuery = $cfg->tableMappings()->where('enabled', true);
            if ($onlyMappingIds !== null && count($onlyMappingIds) > 0) {
                $mappingsQuery->whereIn('id', $onlyMappingIds);
            }
            $mappings = $mappingsQuery->get();

            if ($mappings->isEmpty()) {
                return static::syncEntitiesLegacy($cfg, $dryRun);
            }

            $sorted = $mappings->sortBy(static fn (ExternalEntitiesPgTableMapping $m) => match ($m->target) {
                'provincia' => 0,
                'municipio' => 1,
                'entity'    => 2,
                default     => 9,
            })->values();

            $agg = [
                'provincias' => ['created' => 0, 'updated' => 0],
                'municipios' => ['created' => 0, 'updated' => 0],
                'entities'   => ['created' => 0, 'updated_codes' => 0, 'updated_data' => 0],
            ];
            $recordsAgg = ['provincias' => [], 'municipios' => [], 'entities' => []];
            $municipiosDiagMerged = null;

            $doSync = static function () use ($cfg, $sorted, &$agg, &$recordsAgg, &$municipiosDiagMerged, $dryRun): void {
                foreach ($sorted as $mapping) {
                    if ($mapping->target === 'provincia') {
                        $result = static::syncProvinciasFromMapping($cfg, $mapping, $dryRun);
                        static::mergeProvinciasAgg($agg, $result);
                        $recordsAgg['provincias'] = array_merge($recordsAgg['provincias'], $result['records'] ?? []);
                    } elseif ($mapping->target === 'municipio') {
                        $result = static::syncMunicipiosFromMapping($cfg, $mapping, $dryRun);
                        static::mergeMunicipiosAgg($agg, $result);
                        $recordsAgg['municipios'] = array_merge($recordsAgg['municipios'], $result['records'] ?? []);
                        if ($dryRun && isset($result['diagnostics'])) {
                            $d = $result['diagnostics'];
                            if ($municipiosDiagMerged === null) {
                                $municipiosDiagMerged = $d;
                            } else {
                                $municipiosDiagMerged['source_rows']          += $d['source_rows'];
                                $municipiosDiagMerged['skipped_empty']        += $d['skipped_empty'];
                                $municipiosDiagMerged['skipped_no_provincia'] += $d['skipped_no_provincia'];
                                $municipiosDiagMerged['unchanged_existing']   += $d['unchanged_existing'];
                                $mergedSamples = array_values(array_unique(array_merge(
                                    $municipiosDiagMerged['sample_missing_prov_codes'] ?? [],
                                    $d['sample_missing_prov_codes'] ?? []
                                )));
                                $municipiosDiagMerged['sample_missing_prov_codes'] = array_slice($mergedSamples, 0, 12);
                            }
                        }
                    } elseif ($mapping->target === 'entity') {
                        $result = static::syncEntitiesFromMapping($cfg, $mapping, $dryRun);
                        static::mergeEntitiesAgg($agg, $result);
                        $recordsAgg['entities'] = array_merge($recordsAgg['entities'], $result['records'] ?? []);
                    }
                }
            };

            $dryRun ? $doSync() : DB::transaction($doSync);

            $now = Carbon::now();
            if (! $dryRun) {
                $cfg->last_synced_at = $now;
                static::persistSyncSummary($cfg, [
                    'ok'           => true,
                    'mode'         => 'mappings',
                    'at'           => $now->toIso8601String(),
                    'provincias'   => $agg['provincias'],
                    'municipios'   => $agg['municipios'],
                    'entities'     => $agg['entities'],
                    'created'      => $agg['entities']['created'] ?? 0,
                    'updated_codes' => $agg['entities']['updated_codes'] ?? 0,
                    'updated_data' => $agg['entities']['updated_data'] ?? 0,
                ]);
            }

            $response = [
                'success'       => true,
                'dry_run'       => $dryRun,
                'mode'          => 'mappings',
                'provincias'    => $agg['provincias'],
                'municipios'    => $agg['municipios'],
                'entities'      => $agg['entities'],
                'created'       => $agg['entities']['created'],
                'updated_codes' => $agg['entities']['updated_codes'],
                'updated_data'  => $agg['entities']['updated_data'],
            ];

            if ($dryRun) {
                $response['records']  = $recordsAgg;
                $response['truncated'] = [
                    'provincias' => count($recordsAgg['provincias']) >= self::MAX_PREVIEW_RECORDS,
                    'municipios' => count($recordsAgg['municipios']) >= self::MAX_PREVIEW_RECORDS,
                    'entities'   => count($recordsAgg['entities'])   >= self::MAX_PREVIEW_RECORDS,
                ];
                if ($municipiosDiagMerged !== null) {
                    $response['municipios_diagnostics'] = $municipiosDiagMerged;
                }
            }

            return $response;

        } catch (\Throwable $e) {
            if (! $dryRun) {
                $cfg = ExternalEntitiesPgSetting::current();
                static::persistSyncSummary($cfg, [
                    'ok'    => false,
                    'error' => 'Error al sincronizar: '.static::utf8Safe($e->getMessage()),
                    'phase' => 'sync',
                ]);
            }

            return ['success' => false, 'message' => 'Error al sincronizar: '.static::utf8Safe($e->getMessage())];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Aplicar cambios seleccionados (resultado del dry-run aprobado por el usuario)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Aplica exactamente los registros que el usuario aprobó tras la vista previa.
     * No necesita re-conectar a la BD externa.
     *
     * @param  array{provincias?: list<array>, municipios?: list<array>, entities?: list<array>}  $changes
     */
    public static function applySelectedChanges(array $changes): array
    {
        $applied = ['provincias' => 0, 'municipios' => 0, 'entities' => 0];

        DB::transaction(static function () use ($changes, &$applied): void {
            // ── Provincias ────────────────────────────────────────────────────
            foreach ($changes['provincias'] ?? [] as $item) {
                if (($item['action'] ?? '') === 'create') {
                    Provincia::create([
                        'name'    => $item['name'],
                        'code'    => $item['code'],
                        'sigla_2' => $item['sigla_2'] ?? null,
                        'sigla_3' => $item['sigla_3'] ?? null,
                        'active'  => true,
                    ]);
                    $applied['provincias']++;
                } elseif (($item['action'] ?? '') === 'update' && ! empty($item['local_id'])) {
                    $prov = Provincia::find($item['local_id']);
                    if ($prov) {
                        $prov->update(static::flatChanges($item['changes'] ?? []));
                        $applied['provincias']++;
                    }
                }
            }

            // ── Municipios ────────────────────────────────────────────────────
            foreach ($changes['municipios'] ?? [] as $item) {
                if (($item['action'] ?? '') === 'create') {
                    Municipio::create([
                        'provincia_id'   => (int) $item['provincia_id'],
                        'provincia_code' => $item['provincia_code'] ?? null,
                        'name'           => $item['name'],
                        'code'           => $item['code'],
                        'active'         => true,
                    ]);
                    $applied['municipios']++;
                } elseif (($item['action'] ?? '') === 'update' && ! empty($item['local_id'])) {
                    $mun = Municipio::find($item['local_id']);
                    if ($mun) {
                        $updateData = static::flatChanges($item['changes'] ?? []);
                        if (isset($item['provincia_code'])) {
                            $updateData['provincia_code'] = $item['provincia_code'];
                        }
                        $mun->update($updateData);
                        $applied['municipios']++;
                    }
                }
            }

            // ── Entidades ─────────────────────────────────────────────────────
            foreach ($changes['entities'] ?? [] as $item) {
                if (($item['action'] ?? '') === 'create') {
                    Entity::create([
                        'name'           => $item['name'],
                        'code'           => $item['code'],
                        'municipio_id'   => isset($item['municipio_id']) ? (int) $item['municipio_id'] : null,
                        'municipio_code' => $item['municipio_code'] ?? null,
                        'active'         => true,
                    ]);
                    $applied['entities']++;
                } elseif (($item['action'] ?? '') === 'update' && ! empty($item['local_id'])) {
                    $entity = Entity::find($item['local_id']);
                    if ($entity) {
                        $updateData = static::flatChanges($item['changes'] ?? []);
                        if (isset($item['municipio_code'])) {
                            $updateData['municipio_code'] = $item['municipio_code'];
                        }
                        $entity->update($updateData);
                        $applied['entities']++;
                    }
                }
            }
        });

        $now = ExternalEntitiesPgSetting::current();
        static::persistSyncSummary($now, [
            'ok'   => true,
            'mode' => 'selected',
            'at'   => Carbon::now()->toIso8601String(),
            'provincias' => ['created' => 0, 'updated' => 0, 'applied' => $applied['provincias']],
            'municipios' => ['created' => 0, 'updated' => 0, 'applied' => $applied['municipios']],
            'entities'   => ['created' => 0, 'updated_codes' => 0, 'updated_data' => 0, 'applied' => $applied['entities']],
        ]);

        return [
            'success' => true,
            'applied' => $applied,
        ];
    }

    /**
     * Convierte el dict de cambios `{field: {from, to}}` al array plano `{field: to}`
     * que necesitan los métodos `update()` de Eloquent.
     */
    private static function flatChanges(array $changes): array
    {
        $flat = [];
        foreach ($changes as $field => $diff) {
            $flat[$field] = $diff['to'] ?? $diff;
        }

        return $flat;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sync por tipo de target
    // ──────────────────────────────────────────────────────────────────────────

    private static function syncEntitiesLegacy(ExternalEntitiesPgSetting $cfg, bool $dryRun): array
    {
        $driver    = $cfg->driver ?: 'pgsql';
        $schema    = $cfg->schema_name ?: static::defaultSchema($cfg);
        $tableName = static::qualifiedTableSql($schema, $cfg->table_name, $driver);
        $nameCol   = $cfg->name_column;
        $codeCol   = $cfg->code_column;
        $munCodeCol = $cfg->municipio_code_column;
        $provCol   = $cfg->provincia_column;

        $parts = [
            static::wrapIdent($nameCol, $driver).' AS __n',
            static::wrapIdent($codeCol, $driver).' AS __c',
        ];
        if ($munCodeCol) {
            $parts[] = static::wrapIdent($munCodeCol, $driver).' AS __m';
        }
        if ($provCol) {
            $parts[] = static::wrapIdent($provCol, $driver).' AS __p';
        }

        $sql          = 'SELECT '.implode(', ', $parts).' FROM '.$tableName;
        $created      = 0;
        $updatedCodes = 0;
        $updatedData  = 0;
        $records      = [];

        $doSync = static function () use ($sql, $munCodeCol, $provCol, $dryRun, &$created, &$updatedCodes, &$updatedData, &$records): void {
            foreach (static::iterateRows($sql) as $row) {
                $stats = static::upsertEntityRow(
                    $row, '__n', '__c',
                    $munCodeCol ? '__m' : null,
                    $provCol    ? '__p' : null,
                    $dryRun
                );
                $created      += $stats['created'];
                $updatedCodes += $stats['updated_codes'];
                $updatedData  += $stats['updated_data'];
                if ($dryRun && ! empty($stats['record'])) {
                    $records[] = $stats['record'];
                }
            }
        };

        $dryRun ? $doSync() : DB::transaction($doSync);

        $now = Carbon::now();
        if (! $dryRun) {
            $cfg->last_synced_at = $now;
            static::persistSyncSummary($cfg, [
                'ok'           => true,
                'mode'         => 'legacy',
                'created'      => $created,
                'updated_codes' => $updatedCodes,
                'updated_data' => $updatedData,
                'at'           => $now->toIso8601String(),
            ]);
        }

        $response = [
            'success'       => true,
            'dry_run'       => $dryRun,
            'mode'          => 'legacy',
            'created'       => $created,
            'updated_codes' => $updatedCodes,
            'updated_data'  => $updatedData,
        ];
        if ($dryRun) {
            $response['records']  = ['provincias' => [], 'municipios' => [], 'entities' => $records];
            $response['truncated'] = ['entities' => count($records) >= self::MAX_PREVIEW_RECORDS];
        }

        return $response;
    }

    /** @return array{created: int, updated: int, records: list<array>} */
    private static function syncProvinciasFromMapping(
        ExternalEntitiesPgSetting $cfg,
        ExternalEntitiesPgTableMapping $m,
        bool $dryRun
    ): array {
        $driver = $cfg->driver ?: 'pgsql';
        $schema = $m->schema_name ?: $cfg->schema_name ?: static::defaultSchema($cfg);
        $colS2  = $m->sigla_2_column;
        $colS3  = $m->sigla_3_column;

        $parts = [
            static::wrapIdent($m->name_column, $driver).' AS __n',
            static::wrapIdent($m->code_column, $driver).' AS __c',
        ];
        if ($colS2 !== null && $colS2 !== '') {
            $parts[] = static::wrapIdent($colS2, $driver).' AS __s2';
        }
        if ($colS3 !== null && $colS3 !== '') {
            $parts[] = static::wrapIdent($colS3, $driver).' AS __s3';
        }

        $sql     = 'SELECT '.implode(', ', $parts).' FROM '.static::qualifiedTableSql($schema, $m->table_name, $driver);
        $created = 0;
        $updated = 0;
        $records = [];

        foreach (static::iterateRows($sql) as $row) {
            $name = trim((string) ($row->__n ?? ''));
            $code = trim((string) ($row->__c ?? ''));
            if ($name === '' || $code === '') {
                continue;
            }

            $sigla2 = static::optionalTrimmedString($row, '__s2');
            $sigla3 = static::optionalTrimmedString($row, '__s3');

            $prov = Provincia::query()->where('code', $code)->first()
                ?? Provincia::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if (! $prov) {
                if (! $dryRun) {
                    Provincia::create([
                        'name'    => $name,
                        'code'    => $code,
                        'sigla_2' => $sigla2,
                        'sigla_3' => $sigla3,
                        'active'  => true,
                    ]);
                }
                if ($dryRun && count($records) < self::MAX_PREVIEW_RECORDS) {
                    $records[] = [
                        'action'  => 'create',
                        'name'    => $name,
                        'code'    => $code,
                        'sigla_2' => $sigla2,
                        'sigla_3' => $sigla3,
                    ];
                }
                $created++;
                continue;
            }

            $changes = [];
            if ($prov->code !== $code) {
                $changes['code'] = ['from' => $prov->code, 'to' => $code];
            }
            if ($prov->name !== $name) {
                $changes['name'] = ['from' => $prov->name, 'to' => $name];
            }
            if ($colS2 !== null && $colS2 !== '' && ($prov->sigla_2 ?? null) !== $sigla2) {
                $changes['sigla_2'] = ['from' => $prov->sigla_2, 'to' => $sigla2];
            }
            if ($colS3 !== null && $colS3 !== '' && ($prov->sigla_3 ?? null) !== $sigla3) {
                $changes['sigla_3'] = ['from' => $prov->sigla_3, 'to' => $sigla3];
            }

            if (! empty($changes)) {
                if (! $dryRun) {
                    $prov->update(static::flatChanges($changes));
                }
                if ($dryRun && count($records) < self::MAX_PREVIEW_RECORDS) {
                    $records[] = [
                        'action'       => 'update',
                        'local_id'     => $prov->id,
                        'name'         => $name,
                        'code'         => $code,
                        'current_name' => $prov->name,
                        'current_code' => $prov->code,
                        'sigla_2'      => $sigla2,
                        'sigla_3'      => $sigla3,
                        'changes'      => $changes,
                    ];
                }
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'records' => $records];
    }

    /** @return array{created: int, updated: int, records: list<array>} */
    private static function syncMunicipiosFromMapping(
        ExternalEntitiesPgSetting $cfg,
        ExternalEntitiesPgTableMapping $m,
        bool $dryRun
    ): array {
        $provCol = $m->provincia_code_column;
        if ($provCol === null || $provCol === '') {
            throw new \InvalidArgumentException('Mapeo municipio: falta columna de codigo de provincia.');
        }

        $driver = $cfg->driver ?: 'pgsql';
        $schema = $m->schema_name ?: $cfg->schema_name ?: static::defaultSchema($cfg);
        $sql    = 'SELECT '
            .static::wrapIdent($m->name_column, $driver).' AS __n, '
            .static::wrapIdent($m->code_column, $driver).' AS __c, '
            .static::wrapIdent($provCol, $driver).' AS __p '
            .'FROM '.static::qualifiedTableSql($schema, $m->table_name, $driver);

        $created = 0;
        $updated = 0;
        $records = [];

        $diag = [
            'source_rows'              => 0,
            'skipped_empty'            => 0,
            'skipped_no_provincia'     => 0,
            'unchanged_existing'       => 0,
            'sample_missing_prov_codes' => [],
        ];

        foreach (static::iterateRows($sql) as $row) {
            $diag['source_rows']++;
            $name     = trim((string) ($row->__n ?? ''));
            $code     = trim((string) ($row->__c ?? ''));
            $provCode = trim((string) ($row->__p ?? ''));

            if ($name === '' || $code === '' || $provCode === '') {
                $diag['skipped_empty']++;
                continue;
            }

            $provinciaId = static::findProvinciaIdByExternalCode($provCode);
            if ($provinciaId === null) {
                $diag['skipped_no_provincia']++;
                if (count($diag['sample_missing_prov_codes']) < 8 && ! in_array($provCode, $diag['sample_missing_prov_codes'], true)) {
                    $diag['sample_missing_prov_codes'][] = $provCode;
                }
                continue;
            }

            $mun = Municipio::query()->where('provincia_id', $provinciaId)->where('code', $code)->first()
                ?? Municipio::query()->where('provincia_id', $provinciaId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if (! $mun) {
                if (! $dryRun) {
                    Municipio::create([
                        'provincia_id'   => $provinciaId,
                        'provincia_code' => $provCode,
                        'name'           => $name,
                        'code'           => $code,
                        'active'         => true,
                    ]);
                }
                if ($dryRun && count($records) < self::MAX_PREVIEW_RECORDS) {
                    $records[] = [
                        'action'       => 'create',
                        'name'         => $name,
                        'code'         => $code,
                        'provincia_id' => $provinciaId,
                        'provincia_code' => $provCode,
                    ];
                }
                $created++;
                continue;
            }

            $changes = [];
            if ($mun->code !== $code) {
                $changes['code'] = ['from' => $mun->code, 'to' => $code];
            }
            if ($mun->name !== $name) {
                $changes['name'] = ['from' => $mun->name, 'to' => $name];
            }
            if ((int) $mun->provincia_id !== (int) $provinciaId) {
                $changes['provincia_id'] = ['from' => $mun->provincia_id, 'to' => $provinciaId];
            }

            if (! empty($changes)) {
                if (! $dryRun) {
                    $mun->update(array_merge(static::flatChanges($changes), ['provincia_code' => $provCode]));
                }
                if ($dryRun && count($records) < self::MAX_PREVIEW_RECORDS) {
                    $records[] = [
                        'action'         => 'update',
                        'local_id'       => $mun->id,
                        'name'           => $name,
                        'code'           => $code,
                        'current_name'   => $mun->name,
                        'current_code'   => $mun->code,
                        'provincia_id'   => $provinciaId,
                        'provincia_code' => $provCode,
                        'changes'        => $changes,
                    ];
                }
                $updated++;
            } else {
                $diag['unchanged_existing']++;
            }
        }

        return [
            'created'      => $created,
            'updated'      => $updated,
            'records'      => $records,
            'diagnostics'  => $diag,
        ];
    }

    /** @return array{created: int, updated_codes: int, updated_data: int, records: list<array>} */
    private static function syncEntitiesFromMapping(
        ExternalEntitiesPgSetting $cfg,
        ExternalEntitiesPgTableMapping $m,
        bool $dryRun
    ): array {
        $driver     = $cfg->driver ?: 'pgsql';
        $munCodeCol = $m->municipio_code_column;
        $provCol    = $m->provincia_code_column;
        $schema     = $m->schema_name ?: $cfg->schema_name ?: static::defaultSchema($cfg);

        $parts = [
            static::wrapIdent($m->name_column, $driver).' AS __n',
            static::wrapIdent($m->code_column, $driver).' AS __c',
        ];
        if ($munCodeCol !== null && $munCodeCol !== '') {
            $parts[] = static::wrapIdent($munCodeCol, $driver).' AS __m';
        }
        if ($provCol !== null && $provCol !== '') {
            $parts[] = static::wrapIdent($provCol, $driver).' AS __p_ent';
        }

        $sql          = 'SELECT '.implode(', ', $parts).' FROM '.static::qualifiedTableSql($schema, $m->table_name, $driver);
        $created      = 0;
        $updatedCodes = 0;
        $updatedData  = 0;
        $records      = [];

        foreach (static::iterateRows($sql) as $row) {
            $stats = static::upsertEntityRow(
                $row,
                '__n',
                '__c',
                ($munCodeCol !== null && $munCodeCol !== '') ? '__m' : null,
                ($provCol    !== null && $provCol    !== '') ? '__p_ent' : null,
                $dryRun
            );
            $created      += $stats['created'];
            $updatedCodes += $stats['updated_codes'];
            $updatedData  += $stats['updated_data'];
            if ($dryRun && ! empty($stats['record']) && count($records) < self::MAX_PREVIEW_RECORDS) {
                $records[] = $stats['record'];
            }
        }

        return [
            'created'       => $created,
            'updated_codes' => $updatedCodes,
            'updated_data'  => $updatedData,
            'records'       => $records,
        ];
    }

    /** @return array{created: int, updated_codes: int, updated_data: int, record: array|null} */
    private static function upsertEntityRow(
        object $row,
        string $nameCol,
        string $codeCol,
        ?string $munCodeCol,
        ?string $provCol,
        bool $dryRun
    ): array {
        $created      = 0;
        $updatedCodes = 0;
        $updatedData  = 0;
        $record       = null;

        $name = trim((string) ($row->{$nameCol} ?? ''));
        $code = trim((string) ($row->{$codeCol} ?? ''));
        if ($name === '' || $code === '') {
            return [
            'created'       => $created,
            'updated_codes' => $updatedCodes,
            'updated_data'  => $updatedData,
            'record'        => $record,
        ];
        }

        $munRaw  = ($munCodeCol && isset($row->{$munCodeCol})) ? trim((string) $row->{$munCodeCol}) : '';
        $provRaw = ($provCol    && isset($row->{$provCol}))    ? trim((string) $row->{$provCol})    : '';

        $municipioId = static::resolveMunicipioId($munRaw !== '' ? $munRaw : null, $provRaw !== '' ? $provRaw : null);

        $entity = Entity::query()->where('code', $code)->first()
            ?? Entity::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        $municipioCode = ($munRaw !== '') ? $munRaw : null;

        if (! $entity) {
            if (! $dryRun) {
                Entity::create([
                    'name'          => $name,
                    'code'          => $code,
                    'municipio_id'  => $municipioId,
                    'municipio_code' => $municipioCode,
                    'active'        => true,
                ]);
            }
            if ($dryRun) {
                $record = [
                    'action'         => 'create',
                    'name'           => $name,
                    'code'           => $code,
                    'municipio_id'   => $municipioId,
                    'municipio_code' => $municipioCode,
                ];
            }
            $created++;

            return [
            'created'       => $created,
            'updated_codes' => $updatedCodes,
            'updated_data'  => $updatedData,
            'record'        => $record,
        ];
        }

        $changes = [];
        if ($entity->code !== $code) {
            $changes['code'] = ['from' => $entity->code, 'to' => $code];
        }
        if ($entity->name !== $name) {
            $changes['name'] = ['from' => $entity->name, 'to' => $name];
        }
        if ($municipioId !== null && (int) $entity->municipio_id !== (int) $municipioId) {
            $changes['municipio_id'] = ['from' => $entity->municipio_id, 'to' => $municipioId];
        }

        if (! empty($changes)) {
            $hadCodeChange = array_key_exists('code', $changes);
            if (! $dryRun) {
                $updateData = static::flatChanges($changes);
                if ($municipioCode !== null) {
                    $updateData['municipio_code'] = $municipioCode;
                }
                $entity->update($updateData);
            }
            if ($dryRun) {
                $record = [
                    'action'       => 'update',
                    'local_id'     => $entity->id,
                    'name'         => $name,
                    'code'         => $code,
                    'current_name' => $entity->name,
                    'current_code' => $entity->code,
                    'changes'      => $changes,
                ];
            }
            $hadCodeChange ? $updatedCodes++ : $updatedData++;
        }

        return [
            'created'       => $created,
            'updated_codes' => $updatedCodes,
            'updated_data'  => $updatedData,
            'record'        => $record,
        ];
    }

    private static function resolveMunicipioId(?string $munRaw, ?string $provRaw): ?int
    {
        if ($munRaw === null || $munRaw === '') {
            return null;
        }

        if ($provRaw !== null && $provRaw !== '') {
            $provinciaId = static::findProvinciaIdByExternalCode($provRaw)
                ?? Provincia::query()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($provRaw))])->value('id');

            if ($provinciaId === null) {
                return null;
            }

            return (int) (
                Municipio::query()->where('provincia_id', $provinciaId)->where('code', $munRaw)->value('id')
                ?? Municipio::query()->where('provincia_id', $provinciaId)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($munRaw))])->value('id')
                ?? 0
            ) ?: null;
        }

        return (int) (
            Municipio::query()->where('code', $munRaw)->value('id')
            ?? Municipio::query()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($munRaw))])->value('id')
            ?? 0
        ) ?: null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers de agregación
    // ──────────────────────────────────────────────────────────────────────────

    private static function mergeProvinciasAgg(array &$agg, array $partial): void
    {
        $agg['provincias']['created'] += $partial['created'] ?? 0;
        $agg['provincias']['updated'] += $partial['updated'] ?? 0;
    }

    private static function mergeMunicipiosAgg(array &$agg, array $partial): void
    {
        $agg['municipios']['created'] += $partial['created'] ?? 0;
        $agg['municipios']['updated'] += $partial['updated'] ?? 0;
    }

    /**
     * Busca el id de provincia local a partir del código leído en la BD externa.
     * Tolera ceros a la izquierda (01 vs 1), espacios y varias longitudes de relleno.
     */
    private static function findProvinciaIdByExternalCode(string $provCode): ?int
    {
        $provCode = trim($provCode);
        if ($provCode === '') {
            return null;
        }

        $candidates = [$provCode];
        $stripped   = ltrim($provCode, '0');
        if ($stripped !== '') {
            $candidates[] = $stripped;
        }
        if ($stripped === '' && str_contains($provCode, '0')) {
            $candidates[] = '0';
        }
        if (ctype_digit(str_replace([' ', "\t", "\xc2\xa0"], '', $provCode))) {
            $digits = ltrim(preg_replace('/\D/', '', $provCode) ?? '', '0') ?: '0';
            $candidates[] = str_pad($digits, 2, '0', STR_PAD_LEFT);
            $candidates[] = str_pad($digits, 5, '0', STR_PAD_LEFT);
        }

        $candidates = array_values(array_unique(array_filter($candidates, static fn ($c) => $c !== null && $c !== '')));

        $id = Provincia::query()->whereIn('code', $candidates)->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    private static function mergeEntitiesAgg(array &$agg, array $partial): void
    {
        $agg['entities']['created']       += $partial['created'] ?? 0;
        $agg['entities']['updated_codes'] += $partial['updated_codes'] ?? 0;
        $agg['entities']['updated_data']  += $partial['updated_data'] ?? 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Conexión y drivers
    // ──────────────────────────────────────────────────────────────────────────

    private static function bootConnection(ExternalEntitiesPgSetting $cfg): void
    {
        $driver      = $cfg->driver ?: 'pgsql';
        $defaultPort = match ($driver) {
            'mysql', 'mariadb' => 3306,
            'sqlsrv'           => 1433,
            default            => 5432,
        };

        $base = [
            'driver'   => $driver,
            'host'     => $cfg->host,
            'port'     => $cfg->port ?: $defaultPort,
            'database' => $cfg->database_name,
            'username' => $cfg->username,
            'password' => $cfg->password,
            'prefix'   => '',
            'options'  => [
                \PDO::ATTR_TIMEOUT => (int) ($cfg->timeout ?: 5),
            ],
        ];

        if ($driver === 'pgsql') {
            $base += [
                'charset'        => 'utf8',
                'prefix_indexes' => true,
                'schema'         => $cfg->schema_name ?: 'public',
                'sslmode'        => 'prefer',
            ];
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $base += [
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict'    => false,
            ];
        }

        Config::set('database.connections.'.self::CONNECTION_NAME, $base);
        DB::purge(self::CONNECTION_NAME);
        DB::reconnect(self::CONNECTION_NAME);
    }

    /** Itera filas una por una vía cursor PDO (sin cargar todo en memoria). */
    private static function iterateRows(string $sql): \Generator
    {
        $pdo  = DB::connection(self::CONNECTION_NAME)->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(\PDO::FETCH_OBJ);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers SQL por driver
    // ──────────────────────────────────────────────────────────────────────────

    private static function wrapIdent(string $ident, string $driver = 'pgsql'): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => '`'.str_replace('`', '``', $ident).'`',
            'sqlsrv'           => '['.str_replace(']', ']]', $ident).']',
            default            => '"'.str_replace('"', '""', $ident).'"',
        };
    }

    private static function qualifiedTableSql(string $schema, string $table, string $driver = 'pgsql'): string
    {
        return static::wrapIdent($schema, $driver).'.'.static::wrapIdent($table, $driver);
    }

    private static function defaultSchema(ExternalEntitiesPgSetting $cfg): string
    {
        $driver = $cfg->driver ?: 'pgsql';
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return $cfg->database_name ?: 'public';
        }

        return 'public';
    }

    private static function resolveSchema(ExternalEntitiesPgSetting $cfg, ?string $override): string
    {
        return $override ?? $cfg->schema_name ?: static::defaultSchema($cfg);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Persistencia y utilidades
    // ──────────────────────────────────────────────────────────────────────────

    private static function persistSyncSummary(ExternalEntitiesPgSetting $cfg, array $payload): void
    {
        $at               = $payload['at'] ?? Carbon::now()->toIso8601String();
        $cfg->last_sync_summary = array_merge($payload, ['at' => $at]);
        $cfg->save();
    }

    private static function optionalTrimmedString(object $row, string $key): ?string
    {
        if (! isset($row->{$key})) {
            return null;
        }
        $t = trim((string) $row->{$key});

        return $t === '' ? null : $t;
    }

    private static function utf8Safe(string $value): string
    {
        return mb_scrub($value, 'UTF-8');
    }
}
