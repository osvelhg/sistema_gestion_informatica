<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExcelImportHelpers;
use App\Http\Requests\StoreEquipmentFileRequest;
use App\Models\Department;
use App\Models\Entity;
use App\Models\EquipmentFile;
use App\Models\User;
use App\Services\EquipmentFileService;
use App\Services\ExternalEntityDbService;
use App\Services\LevantamientoEquiposImportParser;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LevantamientoEquiposImportController extends Controller
{
    use ExcelImportHelpers;

    public function __construct(
        private readonly LevantamientoEquiposImportParser $parser,
        private readonly EquipmentFileService $equipmentFileService,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('create', EquipmentFile::class);

        $request->validate([
            // Extensiones admitidas; el validador mimes falla a veces por MIME reportado como octet-stream.
            'excel_file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('Debe adjuntar un archivo Excel.');

                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension());

                    if (! in_array($ext, ['xlsx', 'xls'], true)) {
                        $fail('El archivo debe ser .xlsx o .xls.');
                    }
                },
            ],
        ]);

        try {
            $uploaded = $request->file('excel_file');
            $path = $uploaded->getRealPath();
            if ($path === false || $path === '' || ! is_readable($path)) {
                $path = $uploaded->getPathname();
            }
            $parsed = $this->parser->parse($path);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el Excel: '.$e->getMessage(),
            ], 422);
        }

        $records = [];
        $summary = [
            'total' => 0,
            'matched' => 0,
            'not_in_rodas' => 0,
            'rodas_off' => 0,
            'no_department' => 0,
            'duplicate' => 0,
            'invalid' => 0,
            'no_access' => 0,
        ];

        foreach ($parsed['rows'] as $idx => $row) {
            $summary['total']++;
            $record = $this->buildPreviewRecord((int) $idx, $row, $request->user());
            $record['excel_codigo_entidad'] = trim((string) ($row['codigo_rodas_raw'] ?? ''));
            $records[] = $record;

            match ($record['match_status']) {
                'matched' => $summary['matched']++,
                'not_in_rodas' => $summary['not_in_rodas']++,
                'rodas_off' => $summary['rodas_off']++,
                'no_department' => $summary['no_department']++,
                'duplicate' => $summary['duplicate']++,
                'invalid' => $summary['invalid']++,
                'no_access' => $summary['no_access']++,
                default => null,
            };
        }

        return response()->json([
            'success' => true,
            'sheet_name' => $parsed['sheet_name'],
            'records' => $records,
            'summary' => $summary,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $this->authorize('create', EquipmentFile::class);

        $rules = [
            'defaults' => 'required|array',
            'defaults.type' => 'required|in:PC,Laptop',
            'defaults.status' => 'required|string|max:100',
            'defaults.repairable' => 'required|in:Si,No',
            'defaults.responsibles' => 'required|array|min:1|max:30',
            'defaults.responsibles.*.display_name' => 'required|string|max:255',
            'defaults.responsibles.*.samaccountname' => 'nullable|string|max:100',
            'defaults.responsibles.*.mail' => 'nullable|string|max:255',
            'defaults.responsibles.*.source' => 'required|in:manual,ad',
            'defaults.responsibles.*.trabajador_id' => 'nullable|integer|exists:trabajadores,id',
            'items' => 'required|array|min:1|max:500',
            'items.*.entity_id' => 'required|integer|exists:entidades,id',
            'items.*.department_id' => 'required|integer|exists:departamentos,id',
            'items.*.inventory_number' => 'required|string|max:50',
            'items.*.station_name' => 'nullable|string|max:100',
            'items.*.operating_system' => 'nullable|string|max:200',
            'items.*.chassis' => 'nullable|string|max:100',
            'items.*.caracteristicas' => 'nullable|array',
            'items.*.caracteristicas.*.component_type_slug' => 'nullable|string|max:50',
            'items.*.caracteristicas.*.brand' => 'nullable|string|max:100',
            'items.*.caracteristicas.*.model' => 'nullable|string|max:100',
        ];

        $data = $request->validate($rules);

        $defaults = $data['defaults'];
        $defaults['responsibles'] = $this->cleanResponsibles($defaults['responsibles']);

        $allowed = UserEntityAccess::allowedEntityIds($request->user());

        $created = 0;
        $failed = [];

        foreach ($data['items'] as $pos => $item) {
            $entityId = (int) $item['entity_id'];
            if ($allowed !== null && ($allowed === [] || ! in_array($entityId, array_map('intval', $allowed), true))) {
                $failed[] = [
                    'position' => $pos,
                    'inventory_number' => $item['inventory_number'] ?? '',
                    'message' => 'Sin acceso a la entidad indicada.',
                ];

                continue;
            }

            $dept = Department::query()
                ->where('id', (int) $item['department_id'])
                ->where('entity_id', $entityId)
                ->first();

            if (! $dept) {
                $failed[] = [
                    'position' => $pos,
                    'inventory_number' => $item['inventory_number'] ?? '',
                    'message' => 'El departamento no pertenece a la entidad seleccionada.',
                ];

                continue;
            }

            $payload = [
                'entity_id' => $entityId,
                'department_id' => (int) $item['department_id'],
                'type' => $defaults['type'],
                'inventory_number' => trim((string) $item['inventory_number']),
                'chassis' => isset($item['chassis']) ? trim((string) $item['chassis']) : null,
                'ip_address' => null,
                'station_name' => isset($item['station_name']) ? trim((string) $item['station_name']) : null,
                'operating_system' => isset($item['operating_system']) ? trim((string) $item['operating_system']) : null,
                'status' => $defaults['status'],
                'repairable' => $defaults['repairable'],
                'responsibles' => $defaults['responsibles'],
                'seal_code' => null,
                'caracteristicas' => $item['caracteristicas'] ?? [],
                'perifericos' => [],
                'dispositivos' => [],
            ];

            $storeRules = (new StoreEquipmentFileRequest)->rules();
            $validator = Validator::make($payload, $storeRules);

            if ($validator->fails()) {
                $failed[] = [
                    'position' => $pos,
                    'inventory_number' => $payload['inventory_number'],
                    'message' => $validator->errors()->first(),
                ];

                continue;
            }

            try {
                $this->equipmentFileService->create($validator->validated());
                $created++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'position' => $pos,
                    'inventory_number' => $payload['inventory_number'],
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'failed' => $failed,
            'message' => $created > 0
                ? "Se crearon {$created} expediente(s)."
                : 'No se creo ningun expediente.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildPreviewRecord(int $index, array $row, User $user): array
    {
        $sheetRow = $row['_sheet_row'] ?? null;
        unset($row['_sheet_row']);

        $inv = trim((string) ($row['inventory_number'] ?? ''));
        if ($inv === '') {
            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'invalid',
                'errors' => ['Fila sin numero de inventario (MB).'],
                'entity_id' => null,
                'department_id' => null,
                'entity_name' => null,
                'department_name' => null,
                'payload' => null,
            ];
        }

        if (EquipmentFile::query()->where('inventory_number', $inv)->exists()) {
            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'duplicate',
                'errors' => ['Ya existe un expediente con este numero de inventario.'],
                'entity_id' => null,
                'department_id' => null,
                'entity_name' => null,
                'department_name' => null,
                'payload' => null,
            ];
        }

        $rodas = ExternalEntityDbService::lookupInventoryAcrossEntities($inv, null, $user);

        if (($rodas['success'] ?? false) === false) {
            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'rodas_off',
                'errors' => [trim((string) ($rodas['message'] ?? 'La consulta a RODAS no esta disponible. Configure la BD de entidades o active la consulta.'))],
                'entity_id' => null,
                'department_id' => null,
                'entity_name' => null,
                'department_name' => null,
                'payload' => $this->buildPayloadSlice($row),
            ];
        }

        if (! ($rodas['found'] ?? false)) {
            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'not_in_rodas',
                'errors' => [trim((string) ($rodas['message'] ?? 'No se encontro el inventario en RODAS (servidor contable).'))],
                'entity_id' => null,
                'department_id' => null,
                'entity_name' => null,
                'department_name' => null,
                'payload' => $this->buildPayloadSlice($row),
            ];
        }

        $entityId = (int) ($rodas['entity_id'] ?? 0);
        $entity = Entity::query()->active()->find($entityId);

        if (! $entity) {
            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'not_in_rodas',
                'errors' => ['RODAS devolvio una entidad que no esta activa en SGI.'],
                'entity_id' => null,
                'department_id' => null,
                'entity_name' => null,
                'department_name' => null,
                'payload' => $this->buildPayloadSlice($row),
            ];
        }

        $allowed = UserEntityAccess::allowedEntityIds($user);
        if ($allowed !== null && ($allowed === [] || ! in_array($entityId, array_map('intval', $allowed), true))) {
            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'no_access',
                'errors' => ['No tiene acceso a la entidad '.$entity->name.' indicada por RODAS.'],
                'entity_id' => $entityId,
                'department_id' => null,
                'entity_name' => $entity->name,
                'department_name' => null,
                'payload' => $this->buildPayloadSlice($row),
            ];
        }

        $departmentId = isset($rodas['department_id']) ? (int) $rodas['department_id'] : null;
        if ($departmentId === null || $departmentId === 0) {
            $area = trim((string) ($rodas['codigo_area'] ?? ''));

            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'no_department',
                'errors' => $area !== ''
                    ? ['En RODAS el area es "'.$area.'"; no hay departamento local con ese codigo de area. Elija el departamento en la tabla.']
                    : ['RODAS no devolvio area de responsabilidad; asigne el departamento manualmente.'],
                'entity_id' => $entityId,
                'department_id' => null,
                'entity_name' => $entity->name,
                'department_name' => null,
                'payload' => $this->buildPayloadSlice($row),
            ];
        }

        $dept = Department::query()
            ->active()
            ->where('id', $departmentId)
            ->where('entity_id', $entityId)
            ->first();

        if (! $dept) {
            return [
                'index' => $index,
                'sheet_row' => $sheetRow,
                'match_status' => 'no_department',
                'errors' => ['El departamento devuelto por RODAS no coincide con la entidad en SGI. Elija el departamento en la tabla.'],
                'entity_id' => $entityId,
                'department_id' => null,
                'entity_name' => $entity->name,
                'department_name' => null,
                'payload' => $this->buildPayloadSlice($row),
            ];
        }

        return [
            'index' => $index,
            'sheet_row' => $sheetRow,
            'match_status' => 'matched',
            'errors' => [],
            'entity_id' => $entityId,
            'department_id' => $departmentId,
            'entity_name' => $entity->name,
            'department_name' => $dept->name,
            'payload' => array_merge($this->buildPayloadSlice($row), [
                'entity_id' => $entityId,
                'department_id' => $departmentId,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildPayloadSlice(array $row): array
    {
        $os = isset($row['operating_system']) ? trim((string) $row['operating_system']) : '';
        $ch = isset($row['chassis_note']) ? trim((string) $row['chassis_note']) : '';
        $st = isset($row['station_name']) ? trim((string) $row['station_name']) : '';

        return [
            'inventory_number' => trim((string) ($row['inventory_number'] ?? '')),
            'station_name' => $st !== '' ? mb_substr($st, 0, 100) : null,
            'operating_system' => $os !== '' ? mb_substr($os, 0, 200) : null,
            'chassis' => $ch !== '' ? mb_substr($ch, 0, 100) : null,
            'caracteristicas' => $row['caracteristicas'] ?? [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function cleanResponsibles(array $rows): array
    {
        return collect($rows)->map(function ($row) {
            return [
                'display_name' => isset($row['display_name']) ? trim((string) $row['display_name']) : '',
                'samaccountname' => isset($row['samaccountname']) ? trim((string) $row['samaccountname']) : null,
                'mail' => isset($row['mail']) ? trim((string) $row['mail']) : null,
                'source' => (($row['source'] ?? '') === 'ad') ? 'ad' : 'manual',
                'trabajador_id' => isset($row['trabajador_id']) ? (int) $row['trabajador_id'] ?: null : null,
            ];
        })->all();
    }
}
