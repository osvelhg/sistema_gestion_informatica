<?php

namespace App\Http\Controllers;

use App\Models\AreaVenta;
use App\Models\Entity;
use App\Models\SalesFloor;
use App\Services\ExternalAlmacenesService;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaVentaImportController extends Controller
{
    /** Flags del JSON → columna en areas_venta */
    private const FLAG_MAP = [
        'Abierto'        => 'almacen_abierto',
        'MLC'            => 'almacen_mlc',
        'Exhibicion'     => 'almacen_exhibicion',
        'Interno'        => 'almacen_interno',
        'Merma'          => 'almacen_merma',
        'Gastronomia'    => 'almacen_gastronomia',
        'Insumo'         => 'almacen_insumo',
        'Inversiones'    => 'almacen_inversiones',
        'Boutique'       => 'almacen_boutique',
        'MermaOrigen'    => 'almacen_merma_origen',
        'Consignacion'   => 'almacen_consignacion',
        'Emergente'      => 'almacen_emergente',
        'DespachoDiv'    => 'almacen_despacho_div',
        'Distribuir'     => 'almacen_distribuir',
        'MercanciaVenta' => 'almacen_mercancia_venta',
    ];

    /** Todos los flags disponibles para filtrado */
    private const ALL_FLAGS = [
        'Abierto', 'Exhibicion', 'Interno', 'Merma', 'Gastronomia',
        'Insumo', 'Inversiones', 'Boutique', 'MermaOrigen', 'Consignacion',
        'Emergente', 'DespachoDiv', 'Distribuir', 'MercanciaVenta', 'MLC',
    ];

    /** @return list<string> */
    private function normalizeFlagsFilter(Request $request): array
    {
        $raw = json_decode($request->input('flags_filter', '[]'), true) ?? [];

        return array_values(array_intersect((array) $raw, self::ALL_FLAGS));
    }

    /** Misma lógica AND que en preview: todos los flags elegidos deben ser «verdaderos» en la fila. */
    private function rowPassesFlagsFilter(array $row, array $flagsFilter): bool
    {
        foreach ($flagsFilter as $flag) {
            if (empty($row[$flag]) || $row[$flag] === '0') {
                return false;
            }
        }

        return true;
    }

    // ── Paso 1: analizar archivo ───────────────────────────────────────────────

    /**
     * Analiza el JSON y devuelve:
     *  - grupos por IdUnidad (con entidad vinculada y muestras)
     *  - flags disponibles en el archivo (para el filtro de importación)
     *
     * Body opcional: flags_filter (JSON array) — si viene, solo se agrupan filas que cumplan el AND de flags (igual que en preview).
     */
    public function analyze(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:json|max:10240']);

        $rows = json_decode(file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'JSON inválido.'], 422);
        }

        $flagsFilter = $this->normalizeFlagsFilter($request);

        // Agrupar por IdUnidad solo con filas que pasen el filtro de flags (si hay filtro)
        $grouped = [];
        $rowsMatching = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! $this->rowPassesFlagsFilter($row, $flagsFilter)) {
                continue;
            }
            $rowsMatching++;

            $idUnidad = (int) ($row['IdUnidad'] ?? 0);
            if (! $idUnidad) {
                continue;
            }
            if (! isset($grouped[$idUnidad])) {
                $grouped[$idUnidad] = ['count' => 0, 'sample' => []];
            }
            $grouped[$idUnidad]['count']++;
            if (count($grouped[$idUnidad]['sample']) < 4) {
                $nombre = trim((string) ($row['Almacen'] ?? ''));
                if ($nombre !== '') {
                    $grouped[$idUnidad]['sample'][] = $nombre;
                }
            }
        }

        // Cargar entidades accesibles
        $allowedEntityIds = UserEntityAccess::allowedEntityIds($request->user());
        $entQuery         = Entity::query()->orderBy('code');
        if ($allowedEntityIds !== null) {
            $entQuery->whereIn('id', $allowedEntityIds);
        }
        $entities = $entQuery->get(['id', 'name', 'code'])->keyBy('code');

        $groups = [];
        foreach ($grouped as $idUnidad => $info) {
            $code   = str_pad((string) $idUnidad, 5, '0', STR_PAD_LEFT);
            $entity = $entities->get($code);
            $groups[] = [
                'id_unidad'   => $idUnidad,
                'count'       => $info['count'],
                'sample'      => $info['sample'],
                'entity_id'   => $entity?->id,
                'entity_name' => $entity?->name,
                'entity_code' => $entity?->code ?? $code,
                'matched'     => $entity !== null,
            ];
        }

        // Flags que tienen al menos un valor verdadero en el archivo
        $activeFlags = [];
        foreach (self::ALL_FLAGS as $flag) {
            foreach ($rows as $row) {
                if (! empty($row[$flag]) && $row[$flag] !== '0') {
                    $activeFlags[] = $flag;
                    break;
                }
            }
        }

        return response()->json([
            'success'         => true,
            'groups'          => $groups,
            'active_flags'    => $activeFlags,
            'total_rows'      => count($rows),
            'rows_matching'   => $rowsMatching,
            'flags_filter_on' => $flagsFilter !== [],
        ]);
    }

    // ── Paso 2: preview con filtros y asignaciones de piso ────────────────────

    /**
     * Genera preview filtrado.
     *
     * Body (multipart/form-data):
     *   file               – JSON file
     *   flags_filter       – JSON string: array de flags AND, ej: ["Abierto","Exhibicion"]
     *   entity_assignments – JSON string: objeto { "<id_unidad>": <entidades.id>, ... }. Filas cuyo IdUnidad no tenga entidad asignada se omiten del preview (no se importan).
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:json|max:10240']);

        $rows = json_decode(file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'JSON inválido.'], 422);
        }

        // Filtros de flags (AND): solo se importan registros que cumplan TODOS
        $flagsFilter = $this->normalizeFlagsFilter($request);

        /** @var array<string, int|string> $entityAssignments id_unidad => entity_id */
        $entityAssignments = json_decode($request->input('entity_assignments', '{}'), true) ?? [];

        $entityIds = array_values(array_unique(array_filter(array_map(
            fn ($v) => (int) $v,
            array_values($entityAssignments)
        ), fn ($id) => $id > 0)));

        $allowedEntityIds = UserEntityAccess::allowedEntityIds($request->user());
        if ($allowedEntityIds !== null && $entityIds !== []) {
            foreach ($entityIds as $eid) {
                if (! in_array($eid, array_map('intval', $allowedEntityIds), true)) {
                    return response()->json(['success' => false, 'message' => "Sin acceso a la entidad #{$eid}."], 403);
                }
            }
        }

        $floorsByEntity = collect();
        if ($entityIds !== []) {
            $floorsByEntity = SalesFloor::query()
                ->with('entity:id,name,code')
                ->whereIn('entity_id', $entityIds)
                ->orderBy('name')
                ->get()
                ->groupBy('entity_id');
        }

        // Áreas existentes por almacen_id (IdGerenciaIdAlmacen)
        $existingByAlmacenId = AreaVenta::query()
            ->with('salesFloor:id,name,entity_id')
            ->whereNotNull('almacen_id')
            ->get(['id', 'sales_floor_id', 'name', 'almacen_id', 'id_almacen_local',
                   'almacen_tipo', 'almacen_abierto', 'almacen_mlc'])
            ->keyBy('almacen_id');

        // Áreas existentes por piso + nombre normalizado (fallback)
        $existingByFloorName = [];
        AreaVenta::query()
            ->get(['id', 'sales_floor_id', 'name', 'almacen_id'])
            ->each(function (AreaVenta $a) use (&$existingByFloorName) {
                $key                       = $a->sales_floor_id . ':' . ExternalAlmacenesService::normalizeAreaName($a->name);
                $existingByFloorName[$key] = $a;
            });

        $items  = [];
        $totals = ['create' => 0, 'update' => 0, 'skip' => 0, 'no_piso' => 0, 'filtered_out' => 0, 'excluded_no_entity' => 0];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! $this->rowPassesFlagsFilter($row, $flagsFilter)) {
                $totals['filtered_out']++;
                continue;
            }

            $idGerencia = (int) ($row['IdGerenciaIdAlmacen'] ?? 0);
            $idUnidad   = (int) ($row['IdUnidad'] ?? 0);
            $idAlmacen  = (int) ($row['IdAlmacen'] ?? 0);
            $nombre     = trim((string) ($row['Almacen'] ?? ''));
            $eContable  = trim((string) ($row['EContable'] ?? ''));
            $tipo       = $this->deriveTipo($row);

            $assignedEntityId = isset($entityAssignments[(string) $idUnidad])
                ? (int) $entityAssignments[(string) $idUnidad]
                : 0;

            if ($assignedEntityId <= 0) {
                $totals['excluded_no_entity']++;

                continue;
            }

            $entityFloors = $floorsByEntity->get($assignedEntityId) ?? collect();

            $existing = $existingByAlmacenId->get($idGerencia);
            $floor    = null;

            if ($existing && $existing->salesFloor
                && (int) $existing->salesFloor->entity_id === $assignedEntityId) {
                $floor = $existing->salesFloor;
            } else {
                $existing = null;
            }

            if (! $floor) {
                foreach ($entityFloors as $sf) {
                    $key = $sf->id . ':' . ExternalAlmacenesService::normalizeAreaName($nombre);
                    if (isset($existingByFloorName[$key])) {
                        $existing = $existingByFloorName[$key];
                        $floor    = $sf;
                        break;
                    }
                }
            }

            if (! $floor) {
                $totals['no_piso']++;
                $items[] = $this->buildItem('no_piso', $nombre, $idGerencia, $idAlmacen,
                    $eContable, $tipo, $row, $idUnidad, null, null, $assignedEntityId);
                continue;
            }

            if (! $existing) {
                $existing = $existingByAlmacenId->get($idGerencia);
            }
            if ($existing && (int) $existing->sales_floor_id !== (int) $floor->id) {
                $existing = null;
            }

            if (! $existing) {
                $action = 'create';
                $totals['create']++;
            } else {
                $flags      = $this->buildFlags($row);
                $hasCambios = $existing->almacen_id !== $idGerencia
                    || $existing->id_almacen_local !== $idAlmacen
                    || $existing->almacen_tipo !== $tipo
                    || (bool) $existing->almacen_abierto !== $flags['almacen_abierto']
                    || (bool) $existing->almacen_mlc !== $flags['almacen_mlc'];
                $action     = $hasCambios ? 'update' : 'skip';
                $totals[$action]++;
            }

            $items[] = $this->buildItem($action, $nombre, $idGerencia, $idAlmacen,
                $eContable, $tipo, $row, $idUnidad, $floor, $existing, null);
        }

        return response()->json([
            'success' => true,
            'items'   => $items,
            'totals'  => $totals,
        ]);
    }

    // ── Paso 3: aplicar ───────────────────────────────────────────────────────

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                      => 'required|array|min:1',
            'items.*.action'             => 'required|in:create,update',
            'items.*.almacen_nombre'     => 'required_if:items.*.action,create|string|max:255',
            'items.*.almacen_id'         => 'nullable|integer',
            'items.*.id_almacen_local'   => 'nullable|integer',
            'items.*.almacen_tipo'       => 'nullable|string|max:50',
            'items.*.almacen_e_contable' => 'nullable|string|max:60',
            'items.*.sales_floor_id'     => 'required|integer|exists:pisos_venta,id',
            'items.*.local_area_id'      => 'nullable|integer',
            'items.*.flags'              => 'nullable|array',
        ]);

        // Validar acceso a pisos
        $allowedEntityIds = UserEntityAccess::allowedEntityIds($request->user());
        if ($allowedEntityIds !== null) {
            $floorIds = collect($data['items'])->pluck('sales_floor_id')->unique()->values();
            $allowed  = SalesFloor::whereIn('id', $floorIds)
                ->whereIn('entity_id', $allowedEntityIds)
                ->pluck('id')->all();
            foreach ($floorIds as $fid) {
                if (! in_array($fid, $allowed)) {
                    return response()->json(['success' => false, 'message' => "Sin acceso al piso #{$fid}."], 403);
                }
            }
        }

        $created = 0;
        $updated = 0;
        $errors  = [];

        DB::transaction(function () use ($data, &$created, &$updated, &$errors) {
            foreach ($data['items'] as $item) {
                $action  = $item['action'];
                $floorId = (int) $item['sales_floor_id'];

                $almacenFields = [
                    'almacen_id'         => isset($item['almacen_id']) ? ((int) $item['almacen_id'] ?: null) : null,
                    'id_almacen_local'   => isset($item['id_almacen_local']) ? ((int) $item['id_almacen_local'] ?: null) : null,
                    'almacen_tipo'       => $item['almacen_tipo'] ?? null,
                    'almacen_e_contable' => $item['almacen_e_contable'] ?? null,
                ];

                foreach ($item['flags'] ?? [] as $col => $val) {
                    if (in_array($col, array_values(self::FLAG_MAP), true)) {
                        $almacenFields[$col] = (bool) $val;
                    }
                }

                try {
                    if ($action === 'create') {
                        $nombre = trim($item['almacen_nombre'] ?? '');
                        if ($nombre === '') {
                            $errors[] = 'Nombre vacío, se omite.';
                            continue;
                        }
                        AreaVenta::create(array_merge(
                            ['sales_floor_id' => $floorId, 'name' => $nombre],
                            $almacenFields
                        ));
                        $created++;
                    } elseif ($action === 'update' && ! empty($item['local_area_id'])) {
                        AreaVenta::where('id', (int) $item['local_area_id'])
                            ->where('sales_floor_id', $floorId)
                            ->update($almacenFields);
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        });

        return response()->json([
            'success' => empty($errors) || ($created + $updated) > 0,
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function deriveTipo(array $row): ?string
    {
        $priority = ['MercanciaVenta', 'Exhibicion', 'Gastronomia', 'Boutique',
                     'Insumo', 'Inversiones', 'Consignacion', 'Emergente',
                     'Interno', 'Distribuir', 'Merma', 'MermaOrigen',
                     'ReservaDiv', 'ReservaNac', 'DespachoDiv'];
        foreach ($priority as $flag) {
            if (! empty($row[$flag]) && $row[$flag] !== '0') {
                return $flag;
            }
        }
        return null;
    }

    /** @return array<string, bool> */
    private function buildFlags(array $row): array
    {
        $flags = [];
        foreach (self::FLAG_MAP as $jsonKey => $col) {
            $flags[$col] = isset($row[$jsonKey]) && $row[$jsonKey] !== '0' && $row[$jsonKey] !== 0;
        }
        return $flags;
    }

    /** Entidad local por código IdUnidad (5 dígitos), para filtros de piso en el cliente. */
    private function entityIdForUnidad(int $idUnidad): ?int
    {
        $code = str_pad((string) $idUnidad, 5, '0', STR_PAD_LEFT);

        return Entity::query()->where('code', $code)->value('id');
    }

    private function buildItem(
        string $action,
        string $nombre,
        int $idGerencia,
        int $idAlmacen,
        string $eContable,
        ?string $tipo,
        array $row,
        int $idUnidad,
        ?SalesFloor $floor,
        ?AreaVenta $existing,
        ?int $forcedEntityId = null,
    ): array {
        $entityId = $floor?->entity_id ?? $forcedEntityId ?? $this->entityIdForUnidad($idUnidad);

        return [
            'action'             => $action,
            'almacen_nombre'     => $nombre,
            'almacen_id'         => $idGerencia,
            'id_almacen_local'   => $idAlmacen,
            'almacen_e_contable' => $eContable,
            'almacen_tipo'       => $tipo,
            'flags'              => $this->buildFlags($row),
            'id_unidad'          => $idUnidad,
            'entity_id'          => $entityId,
            'sales_floor_id'     => $floor?->id,
            'floor_name'         => $floor?->name,
            'local_area_id'   => $existing?->id,
            'local_area_name' => $existing?->name,
        ];
    }
}
