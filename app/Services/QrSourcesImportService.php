<?php

namespace App\Services;

use App\Models\CanalElectronico;
use App\Models\DatacellSource;
use App\Models\Moneda;
use App\Models\SalesFloor;
use App\Models\TipoFuente;

class QrSourcesImportService
{
    /**
     * Claves lógicas → claves JSON por defecto (export estándar).
     *
     * @var array<string, string>
     */
    public const DEFAULT_FIELD_MAP = [
        'external_id'    => 'Id',
        'source'           => 'Source',
        'source_name'      => 'SourceName',
        'moneda'           => 'Moneda',
        'id_unidad'        => 'IdUnidad',
        'unidad_nombre'    => 'UnidadNombre',
        'id_canal'         => 'IdCanal',
        'canal_nombre'     => 'CanalNombre',
        'id_tipo_source'   => 'IdTipoSource',
        'tipo_nombre'      => 'TipoNombre',
        'activo'           => 'Activo',
        'id_piso'          => 'IdPiso',
        'almacen'          => 'Almacen',
        'id_division'      => 'IdDivision',
        'division'         => 'Division',
    ];

    /**
     * @param  array<string, string|null>  $userMap  clave lógica → nombre de propiedad en el JSON
     * @return array{success: bool, error?: string, created?: int, updated?: int, total?: int, skipped?: int, skipped_canal?: int}
     */
    public static function syncFromJson(array $raw, ?array $userMap = null, ?string $canalNombreFilter = null): array
    {
        $items = static::normalizeQrSourcesPayload($raw);

        if (empty($items)) {
            return ['success' => false, 'error' => 'El JSON no contiene datos reconocibles.'];
        }

        $map = static::mergeFieldMap($userMap);

        $filter = $canalNombreFilter !== null && trim($canalNombreFilter) !== ''
            ? trim($canalNombreFilter)
            : null;

        return static::processItems($items, $map, $filter);
    }

    /**
     * @param  array<string, string|null>  $userMap
     * @return array<string, string>
     */
    public static function mergeFieldMap(?array $userMap): array
    {
        $base = self::DEFAULT_FIELD_MAP;
        if (empty($userMap)) {
            return $base;
        }
        foreach ($base as $logical => $defaultJsonKey) {
            if (array_key_exists($logical, $userMap) && is_string($userMap[$logical]) && $userMap[$logical] !== '') {
                $base[$logical] = $userMap[$logical];
            }
        }

        return $base;
    }

    /**
     * @param  array<string, string>  $map
     */
    public static function valueFrom(array $item, array $map, string $logicalField): mixed
    {
        $jsonKey = $map[$logicalField] ?? null;
        if ($jsonKey === null || $jsonKey === '') {
            return null;
        }

        return $item[$jsonKey] ?? null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    public static function normalizeQrSourcesPayload(array $decoded): array
    {
        if ($decoded === []) {
            return [];
        }

        $topKeys = [
            'data', 'items', 'result', 'sources', 'fuentes',
            'qrsource', 'QrSource', 'qrSources', 'QrSources',
            'sourceqr', 'SourceQr',
        ];
        foreach ($topKeys as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key]) && array_is_list($decoded[$key])) {
                return $decoded[$key];
            }
        }

        if (isset($decoded['division']) && is_array($decoded['division'])) {
            $div = $decoded['division'];
            foreach ($topKeys as $key) {
                if (isset($div[$key]) && is_array($div[$key]) && array_is_list($div[$key])) {
                    return $div[$key];
                }
            }
            if (array_is_list($div)) {
                return $div;
            }
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, string>  $map
     * @return array{success: bool, created: int, updated: int, total: int, skipped: int, skipped_canal: int}
     */
    private static function processItems(array $items, array $map, ?string $canalNombreFilter = null): array
    {
        $now     = now();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skippedCanal = 0;

        $canalesCache = [];
        $tiposCache   = [];
        $monedasCache = [];

        $wantNorm = $canalNombreFilter !== null
            ? strtoupper(preg_replace('/\s+/u', '', $canalNombreFilter))
            : null;

        foreach ($items as $item) {
            if ($wantNorm !== null && $wantNorm !== '') {
                $nombreFila = static::valueFrom($item, $map, 'canal_nombre');
                $rowNorm = strtoupper(preg_replace('/\s+/u', '', (string) ($nombreFila ?? '')));
                if ($rowNorm === '' || $rowNorm !== $wantNorm) {
                    $skippedCanal++;

                    continue;
                }
            }

            $canalElectronicoId = null;
            $idCanal = static::valueFrom($item, $map, 'id_canal');
            if ($idCanal !== null && $idCanal !== '') {
                $apiCanalId = (int) $idCanal;

                if (!isset($canalesCache[$apiCanalId])) {
                    $nombreCanal = static::valueFrom($item, $map, 'canal_nombre');
                    $canal = CanalElectronico::firstOrCreate(
                        ['datacell_id' => $apiCanalId],
                        ['nombre' => $nombreCanal ? (string) $nombreCanal : "Canal {$apiCanalId}", 'estado' => true]
                    );
                    if ($canal->wasRecentlyCreated === false && $nombreCanal !== null && $nombreCanal !== '') {
                        $canal->update(['nombre' => (string) $nombreCanal]);
                    }
                    $canalesCache[$apiCanalId] = $canal->id;
                }
                $canalElectronicoId = $canalesCache[$apiCanalId];
            }

            $tipoFuenteId = null;
            $idTipo = static::valueFrom($item, $map, 'id_tipo_source');
            if ($idTipo !== null && $idTipo !== '') {
                $apiTipoId = (int) $idTipo;

                if (!isset($tiposCache[$apiTipoId])) {
                    $nombreTipo = static::valueFrom($item, $map, 'tipo_nombre');
                    $tipo = TipoFuente::firstOrCreate(
                        ['datacell_id' => $apiTipoId],
                        ['nombre' => $nombreTipo ? (string) $nombreTipo : "Tipo {$apiTipoId}", 'estado' => true]
                    );
                    if ($tipo->wasRecentlyCreated === false && $nombreTipo !== null && $nombreTipo !== '') {
                        $tipo->update(['nombre' => (string) $nombreTipo]);
                    }
                    $tiposCache[$apiTipoId] = $tipo->id;
                }
                $tipoFuenteId = $tiposCache[$apiTipoId];
            }

            $salesFloorId = null;
            $idPisoVal = static::valueFrom($item, $map, 'id_piso');
            if ($idPisoVal !== null && $idPisoVal !== '') {
                $idPiso = (int) $idPisoVal;
                $sf = SalesFloor::where('datacell_piso_id', $idPiso)->first();
                $salesFloorId = $sf?->id;
            }

            $monedaRaw = static::valueFrom($item, $map, 'moneda');
            $siglaMoneda = strtoupper(trim((string) ($monedaRaw ?? 'CUP')));
            if ($siglaMoneda === '') {
                $siglaMoneda = 'CUP';
            }
            if (!isset($monedasCache[$siglaMoneda])) {
                $moneda = Moneda::withTrashed()->firstOrNew(['sigla' => $siglaMoneda]);
                if (!$moneda->exists) {
                    $moneda->fill([
                        'nombre'      => $siglaMoneda,
                        'simbolo'     => null,
                        'tasa_cambio' => 1,
                        'estado'      => true,
                    ]);
                } elseif ($moneda->trashed()) {
                    $moneda->restore();
                }
                $moneda->save();
                $monedasCache[$siglaMoneda] = true;
            }

            $externalRaw = static::valueFrom($item, $map, 'external_id');
            $externalId = $externalRaw !== null && $externalRaw !== '' ? (int) $externalRaw : null;

            $sourceRaw = static::valueFrom($item, $map, 'source');
            $sourceStr = $sourceRaw !== null && $sourceRaw !== '' ? (string) $sourceRaw : '';

            $activoRaw = static::valueFrom($item, $map, 'activo');
            $activo = filter_var($activoRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo === null) {
                $activo = (bool) ($activoRaw ?? true);
            }

            $idUnidadRaw = static::valueFrom($item, $map, 'id_unidad');
            $idUnidad = (int) ($idUnidadRaw ?? 0);

            $idPisoForRow = static::valueFrom($item, $map, 'id_piso');
            $idPisoInt = ($idPisoForRow !== null && $idPisoForRow !== '') ? (int) $idPisoForRow : null;

            $idDivRaw = static::valueFrom($item, $map, 'id_division');
            $idDivision = ($idDivRaw !== null && $idDivRaw !== '') ? (int) $idDivRaw : null;

            $unidadNombre = static::valueFrom($item, $map, 'unidad_nombre');
            $unidadNombre = $unidadNombre !== null && $unidadNombre !== '' ? (string) $unidadNombre : null;

            $divisionStr = static::valueFrom($item, $map, 'division');
            $divisionStr = $divisionStr !== null && $divisionStr !== '' ? (string) $divisionStr : null;

            $sourceNameVal = static::valueFrom($item, $map, 'source_name');
            $sourceName = $sourceNameVal !== null && $sourceNameVal !== '' ? (string) $sourceNameVal : null;

            $almacenRaw = static::valueFrom($item, $map, 'almacen');
            $almacenGoldenStr = $almacenRaw !== null && $almacenRaw !== '' ? trim((string) $almacenRaw) : null;

            $data = [
                'external_id'          => $externalId,
                'source'               => $sourceStr,
                'source_name'          => $sourceName,
                'moneda'               => $siglaMoneda,
                'canal_electronico_id' => $canalElectronicoId,
                'tipo_fuente_id'       => $tipoFuenteId,
                'id_unidad'            => $idUnidad,
                'unidad_nombre'        => $unidadNombre,
                'activo'               => $activo,
                'id_piso'              => $idPisoInt,
                'id_division'          => $idDivision,
                'division'             => $divisionStr,
                'synced_at'            => $now,
            ];

            if ($sourceStr === '') {
                $skipped++;

                continue;
            }

            $existing = $externalId
                ? DatacellSource::where('external_id', $externalId)->first()
                : DatacellSource::where('source', $sourceStr)->first();

            if ($existing) {
                if ($salesFloorId !== null) {
                    $data['sales_floor_id'] = $salesFloorId;
                } elseif ($existing->sales_floor_id !== null) {
                    if ($idPisoInt !== null) {
                        $floorPatch = [
                            'datacell_piso_id' => (string) $idPisoInt,
                        ];
                        if ($almacenGoldenStr !== null) {
                            $floorPatch['almacen_golden'] = $almacenGoldenStr;
                        }
                        SalesFloor::where('id', $existing->sales_floor_id)
                            ->whereNull('datacell_piso_id')
                            ->update($floorPatch);
                    }
                }
                $existing->update($data);
                $existing->refresh();
                $updated++;
                $linkedFloorId = $existing->sales_floor_id ?? $salesFloorId;
                static::syncGoldenPisoFieldsOnSalesFloor(
                    $linkedFloorId !== null ? (int) $linkedFloorId : null,
                    $idPisoInt,
                    $almacenGoldenStr
                );
            } else {
                if ($salesFloorId !== null) {
                    $data['sales_floor_id'] = $salesFloorId;
                }
                $createdSource = DatacellSource::create($data);
                $created++;
                static::syncGoldenPisoFieldsOnSalesFloor(
                    $createdSource->sales_floor_id !== null ? (int) $createdSource->sales_floor_id : null,
                    $idPisoInt,
                    $almacenGoldenStr
                );
            }
        }

        return [
            'success'       => true,
            'created'       => $created,
            'updated'       => $updated,
            'total'         => count($items),
            'skipped'       => $skipped,
            'skipped_canal' => $skippedCanal,
        ];
    }

    public static function uniqueUnidades(): array
    {
        return SalesFloor::select('id', 'name')
            ->distinct()
            ->orderBy('name')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'nombre' => $r->name])
            ->toArray();
    }

    /**
     * Copia IdPiso y Almacen del JSON Golden al piso de venta vinculado (importación Datacell).
     */
    private static function syncGoldenPisoFieldsOnSalesFloor(?int $salesFloorId, ?int $idPisoInt, ?string $almacenGoldenStr): void
    {
        if ($salesFloorId === null) {
            return;
        }

        $patch = [];
        if ($idPisoInt !== null) {
            $patch['codigo_golden'] = (string) $idPisoInt;
        }
        if ($almacenGoldenStr !== null && $almacenGoldenStr !== '') {
            $patch['almacen_golden'] = $almacenGoldenStr;
        }

        if ($patch === []) {
            return;
        }

        SalesFloor::whereKey($salesFloorId)->update($patch);
    }
}
