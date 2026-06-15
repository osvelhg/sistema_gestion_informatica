<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveEquipmentRequest;
use App\Http\Requests\StoreEquipmentFileRequest;
use App\Http\Requests\UpdateEquipmentFileRequest;
use App\Models\Department;
use App\Models\ComponentType;
use App\Models\IncidentType;
use App\Models\Entity;
use App\Models\EquipmentFile;
use App\Models\ExpedienteAlerta;
use App\Models\ExternalEntityDbSetting;
use App\Models\LdapSetting;
use App\Models\Trabajador;
use App\Models\Status;
use App\Services\EquipmentFileService;
use App\Services\ExternalEntityDbService;
use App\Services\LdapService;
use App\Services\MovementService;
use App\Support\TabularExport;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EquipmentFileController extends Controller
{
    public function __construct(
        private readonly EquipmentFileService $service,
        private readonly MovementService $movementService,
    ) {}

    public function alertsIndex(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', EquipmentFile::class);

        $alerts = ExpedienteAlerta::query()
            ->whereHas('equipmentFile', function ($q) use ($request) {
                UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id');
            })
            ->with([
                'equipmentFile' => fn ($q) => $q->with(['entity:id,name', 'department:id,name']),
            ])
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return Inertia::render('ExpedienteAlertas/Index', [
            'alerts' => $alerts,
        ]);
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', EquipmentFile::class);

        $files = EquipmentFile::query()
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id'))
            ->with(['entity', 'department', 'creator'])
            ->search($request->get('search'))
            ->filterEntity($request->get('entity_id'))
            ->filterDepartment($request->get('department_id'))
            ->filterStatus($request->get('status'))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('EquipmentFiles/Index', [
            'files'      => $files,
            'filters'    => $request->only(['search', 'entity_id', 'department_id', 'status']),
            'entities'   => Entity::active()
                ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, $request->user()))
                ->get(['id', 'name', 'code']),
            'statuses'   => Status::active()->orderBy('order')->get(['id', 'name', 'color']),
            'statistics' => $this->service->statistics(
                $request->get('entity_id'),
                $request->get('department_id'),
                $request->user(),
            ),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', EquipmentFile::class);

        $extDb = ExternalEntityDbSetting::current();

        $ldap = LdapSetting::current();

        return Inertia::render('EquipmentFiles/Create', [
            'entities'       => Entity::active()->with('departments:id,entity_id,name,code,codigo_area')
                ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, request()->user()))
                ->get(['id', 'name', 'code']),
            'componentTypes' => ComponentType::active()->orderBy('name')->get(['id', 'slug', 'name', 'category']),
            'statuses'       => Status::active()->orderBy('order')->get(['id', 'name', 'color']),
            'rodas_lookup_enabled' => (bool) ($extDb->enabled && $extDb->host),
            'ldap_responsible_search_enabled' => (bool) ($ldap->enabled && $ldap->host),
        ]);
    }

    /**
     * Búsqueda local de trabajadores (reduce llamadas LDAP): por nombre, CI o usuario de red.
     */
    public function searchResponsibleTrabajadores(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('expedientes.store') || $request->user()->can('expedientes.update'),
            403
        );

        $data = $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['query']).'%';

        $rows = Trabajador::query()
            ->where('estado', true)
            ->where(function ($q) use ($term) {
                $q->where('nombre', 'ilike', $term)
                    ->orWhere('samaccountname', 'ilike', $term)
                    ->orWhere('ci', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term);
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'nombre', 'samaccountname', 'email', 'cargo', 'origen']);

        return response()->json([
            'trabajadores' => $rows->map(fn (Trabajador $t) => [
                'id'             => $t->id,
                'nombre'         => $t->nombre,
                'samaccountname' => $t->samaccountname,
                'mail'           => $t->email,
                'cargo'          => $t->cargo,
                'origen'         => $t->origen,
            ]),
        ]);
    }

    public function searchResponsibleLdap(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('expedientes.store') || $request->user()->can('expedientes.update'),
            403
        );

        $cfg = LdapSetting::current();
        if (! $cfg->enabled || ! $cfg->host) {
            return response()->json([
                'users'   => [],
                'message' => 'LDAP no está activo en configuración.',
            ], 422);
        }

        $data = $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        if (! LdapService::registerConnectionFromDatabaseIfReady()) {
            return response()->json([
                'users'   => [],
                'message' => 'No se pudo usar el directorio LDAP. Revisa la configuración.',
            ], 503);
        }

        $users = LdapService::searchUsers($data['query']);

        $enriched = collect($users)->map(function ($u) {
            $existing = Trabajador::findExistingMatch(
                null,
                $u['samaccountname'] ?? null,
                $u['displayname'] ?? null,
            );
            $u['trabajador_existente'] = $existing ? [
                'id'     => $existing->id,
                'nombre' => $existing->nombre,
            ] : null;

            return $u;
        })->all();

        return response()->json(['users' => $enriched]);
    }

    public function lookupInventory(Request $request): JsonResponse
    {
        $this->authorize('create', EquipmentFile::class);

        $data = $request->validate([
            'inventory_number' => 'required|string|max:50',
        ]);

        $result = ExternalEntityDbService::lookupInventoryAcrossEntities(
            $data['inventory_number'],
            null,
            $request->user()
        );

        return response()->json($result, $result['success'] === false ? 422 : 200);
    }

    /**
     * Valida inventario de periférico/dispositivo contra RODAS (entidad + departamento del formulario).
     * Si hay expediente y se solicita persistencia, registra alerta en expediente_alertas.
     */
    public function validateMedioInventory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_number'       => 'required|string|max:50',
            'entity_id'              => 'required|exists:entidades,id',
            'department_id'          => 'required|exists:departamentos,id',
            'category'               => 'required|in:periferico,dispositivo',
            'component_type_slug'    => 'nullable|string|max:50',
            'component_type_label'   => 'nullable|string|max:150',
            'equipment_file_id'      => 'nullable|exists:expedientes_equipos,id',
            'persist_alert'          => 'sometimes|boolean',
        ]);

        $equipmentFileId = $data['equipment_file_id'] ?? null;
        $persist           = (bool) ($data['persist_alert'] ?? false);

        if ($equipmentFileId) {
            $this->authorize('update', EquipmentFile::findOrFail($equipmentFileId));
        } else {
            $this->authorize('create', EquipmentFile::class);
        }

        $cfg = ExternalEntityDbSetting::current();
        if (! $cfg->enabled || ! $cfg->host) {
            return response()->json([
                'success' => false,
                'message' => 'La consulta a RODAS no está activa en configuración.',
            ], 422);
        }

        $entity = Entity::find($data['entity_id']);
        if (! $entity) {
            return response()->json(['success' => false, 'message' => 'Entidad no válida.'], 422);
        }

        $allowed = UserEntityAccess::allowedEntityIds($request->user());
        if ($allowed !== null && ($allowed === [] || ! in_array((int) $entity->id, array_map('intval', $allowed), true))) {
            return response()->json(['success' => false, 'message' => 'No tiene acceso a esta entidad.'], 403);
        }

        $inv = trim($data['inventory_number']);
        if ($inv === '') {
            return response()->json(['success' => false, 'message' => 'Indique el número de inventario.'], 422);
        }

        $categoriaTxt = $data['category'] === 'periferico' ? 'Periférico' : 'Otro dispositivo';
        $etiqueta     = $data['component_type_label'] ?: ($data['component_type_slug'] ?: 'Medio');

        $rodas        = null;
        $lookupError  = null;
        try {
            $rodas = ExternalEntityDbService::lookupActivoForEntity($cfg, $entity, $inv);
        } catch (\Throwable $e) {
            $lookupError = $e;
        } finally {
            ExternalEntityDbService::purgeDynamicConnection();
        }

        if ($lookupError !== null) {
            $alertCreated = false;
            if ($persist && $equipmentFileId) {
                ExpedienteAlerta::create([
                    'equipment_file_id' => $equipmentFileId,
                    'type'                => ExpedienteAlerta::TYPE_RODAS_MEDIO_INVENTARIO_INEXISTENTE,
                    'message'             => "{$categoriaTxt} «{$etiqueta}» (inv. {$inv}): error al consultar RODAS — ".$lookupError->getMessage(),
                    'meta'                => [
                        'origen'             => 'validacion_inline',
                        'category'           => $data['category'],
                        'component_type'     => $data['component_type_slug'],
                        'inventory_number'   => $inv,
                        'entity_id'          => (int) $data['entity_id'],
                    ],
                ]);
                $alertCreated = true;
            }

            return response()->json([
                'success'        => true,
                'found'          => false,
                'match'          => false,
                'message'        => 'No se pudo verificar el inventario en RODAS: '.$lookupError->getMessage(),
                'alert_created'  => $alertCreated,
            ]);
        }

        if (! $rodas['found']) {
            $alertCreated = false;
            if ($persist && $equipmentFileId) {
                ExpedienteAlerta::create([
                    'equipment_file_id' => $equipmentFileId,
                    'type'                => ExpedienteAlerta::TYPE_RODAS_MEDIO_INVENTARIO_INEXISTENTE,
                    'message'             => "{$categoriaTxt} «{$etiqueta}» (inv. {$inv}): no consta en activos de RODAS para esta entidad.",
                    'meta'                => [
                        'origen'           => 'validacion_inline',
                        'category'         => $data['category'],
                        'component_type'   => $data['component_type_slug'],
                        'inventory_number' => $inv,
                        'entity_id'        => (int) $data['entity_id'],
                    ],
                ]);
                $alertCreated = true;
            }

            return response()->json([
                'success'       => true,
                'found'         => false,
                'match'         => false,
                'message'       => 'Este inventario no aparece en RODAS para la entidad seleccionada. Puede continuar; si el expediente ya existe, se ha registrado una alerta para revisión.',
                'alert_created' => $alertCreated,
            ]);
        }

        $deptForm         = Department::find($data['department_id']);
        $codigoSeleccionado = $deptForm ? trim((string) $deptForm->codigo_area) : '';
        $codigoRodas      = trim((string) ($rodas['codigo_area'] ?? ''));
        $deptIdRodas      = $rodas['department_id'] ?? null;

        $areaCoincide = $codigoRodas !== ''
            && $codigoSeleccionado !== ''
            && $codigoRodas === $codigoSeleccionado;

        $deptoIdCoincide = $deptIdRodas === null
            || (int) $data['department_id'] === (int) $deptIdRodas;

        $match = $areaCoincide && $deptoIdCoincide;

        if (! $match) {
            $alertCreated = false;
            if ($persist && $equipmentFileId) {
                ExpedienteAlerta::create([
                    'equipment_file_id' => $equipmentFileId,
                    'type'                => ExpedienteAlerta::TYPE_RODAS_MEDIO_INCONGRUENCIA,
                    'message'             => "{$categoriaTxt} «{$etiqueta}» (inv. {$inv}): el área en RODAS no coincide con el departamento del expediente.",
                    'meta'                => [
                        'origen'                   => 'validacion_inline',
                        'category'                 => $data['category'],
                        'component_type'           => $data['component_type_slug'],
                        'inventory_number'       => $inv,
                        'entity_id'                => (int) $data['entity_id'],
                        'department_id'          => (int) $data['department_id'],
                        'codigo_area_rodas'      => $codigoRodas,
                        'codigo_area_expediente'   => $codigoSeleccionado,
                        'department_id_rodas'      => $deptIdRodas,
                    ],
                ]);
                $alertCreated = true;
            }

            return response()->json([
                'success'       => true,
                'found'         => true,
                'match'         => false,
                'message'       => 'El inventario existe en RODAS pero el área de responsabilidad no coincide con el departamento (y entidad) indicados en el expediente. Puede continuar; si el expediente ya existe, se ha registrado una alerta.',
                'alert_created' => $alertCreated,
                'codigo_area_rodas' => $codigoRodas,
            ]);
        }

        return response()->json([
            'success' => true,
            'found'   => true,
            'match'   => true,
            'message' => 'Inventario coherente con RODAS para la entidad y departamento actuales.',
        ]);
    }

    public function store(StoreEquipmentFileRequest $request): RedirectResponse
    {
        $file = $this->service->create($request->validated());

        return redirect()
            ->route('expedientes.show', $file)
            ->with('success', "Expediente {$file->file_number} creado correctamente.");
    }

    public function show(EquipmentFile $expediente): InertiaResponse
    {
        $this->authorize('view', $expediente);

        $expediente->load([
            'entity', 'department', 'creator', 'expedienteAlertas', 'responsibles',
            'components.componentType', 'seals.incidentType', 'movements.fromEntity',
            'movements.toEntity', 'movements.movedBy',
            'inspectionRecords' => fn ($query) => $query->latest('inspection_date')->limit(5),
            'workSheetRecords' => fn ($query) => $query->latest('work_date')->limit(5),
            'securityIncidentRecords' => fn ($query) => $query->latest('incident_date')->latest('incident_time')->limit(5),
            'supportControlRecords' => fn ($query) => $query->latest('record_date')->limit(5),
        ]);

        return Inertia::render('EquipmentFiles/Show', [
            'file' => $expediente,
            'movementEntities' => Entity::active()->with('departments:id,entity_id,name')
                ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, request()->user()))
                ->get(['id', 'name']),
            'incidentTypes' => IncidentType::active()->orderBy('name')->get(['id', 'name']),
            'historyModules' => [
                [
                    'key' => 'inspecciones',
                    'title' => 'Registro de inspecciones',
                    'description' => 'Resumen historico de inspecciones realizadas al equipo y situaciones detectadas.',
                    'count' => $expediente->inspectionRecords()->count(),
                    'manage_url' => route('inspecciones.index', ['equipment_file_id' => $expediente->id, 'source' => 'show', 'entity_id' => $expediente->entity_id, 'department_id' => $expediente->department_id]),
                    'latest' => $expediente->inspectionRecords->map(fn ($record) => [
                        'id' => $record->id,
                        'title' => $record->inspection_date,
                        'subtitle' => $record->participants ?: 'Sin participantes registrados',
                        'detail' => $record->situations_detected,
                    ]),
                ],
                [
                    'key' => 'hojas-trabajo',
                    'title' => 'Hojas de trabajo',
                    'description' => 'Evidencias operativas, controles y observaciones tecnicas asociadas al expediente.',
                    'count' => $expediente->workSheetRecords()->count(),
                    'manage_url' => route('hojas-trabajo.index', ['equipment_file_id' => $expediente->id, 'source' => 'show']),
                    'latest' => $expediente->workSheetRecords->map(function ($record) {
                        $checklist = $record->checklist ?? [];
                        $all = array_values($checklist);
                        $mal = count(array_filter($all, fn ($v) => $v === 'M'));
                        $evaluated = count(array_filter($all, fn ($v) => $v !== null));
                        $checklistDetail = $evaluated > 0
                            ? ($mal > 0 ? "{$mal} elemento(s) en mal estado" : 'Sin incidencias en el checklist')
                            : null;

                        return [
                            'id'       => $record->id,
                            'title'    => ($record->worksheet_number ? "Hoja {$record->worksheet_number}" : 'Sin número') . ' — ' . $record->work_date,
                            'subtitle' => trim(($record->control_area ?: '') . ($record->controlled_area ? ' → ' . $record->controlled_area : '')),
                            'detail'   => $checklistDetail ?? $record->preliminary_results ?? ($record->observations ?? 'Sin detalles registrados'),
                        ];
                    }),
                ],
                [
                    'key' => 'incidencias-seguridad',
                    'title' => 'Incidencias de seguridad',
                    'description' => 'Eventos, hechos detectados, vias de deteccion y medidas aplicadas al equipo.',
                    'count' => $expediente->securityIncidentRecords()->count(),
                    'manage_url' => route('incidencias-seguridad.index', ['equipment_file_id' => $expediente->id, 'source' => 'show', 'area' => $expediente->department?->name]),
                    'latest' => $expediente->securityIncidentRecords->map(fn ($record) => [
                        'id' => $record->id,
                        'title' => trim(($record->incident_date ?: '') . ' ' . ($record->incident_time ?: '')),
                        'subtitle' => $record->area ?: 'Area no especificada',
                        'detail' => $record->detected_fact,
                    ]),
                ],
                [
                    'key' => 'control-soportes',
                    'title' => 'Control de soportes',
                    'description' => 'Trazabilidad de soportes, medios o respaldos vinculados a este expediente.',
                    'count' => $expediente->supportControlRecords()->count(),
                    'manage_url' => route('control-soportes.index', ['equipment_file_id' => $expediente->id, 'source' => 'show', 'area' => $expediente->department?->name]),
                    'latest' => $expediente->supportControlRecords->map(fn ($record) => [
                        'id' => $record->id,
                        'title' => $record->record_date ?: 'Sin fecha',
                        'subtitle' => 'Soporte ' . $record->support_number,
                        'detail' => $record->content_summary ?: ($record->observations ?: 'Sin detalles registrados'),
                    ]),
                ],
            ],
        ]);
    }

    public function edit(EquipmentFile $expediente): InertiaResponse
    {
        $this->authorize('update', $expediente);

        $expediente->load(['components.componentType', 'responsibles']);

        $extDb = ExternalEntityDbSetting::current();
        $ldap = LdapSetting::current();

        return Inertia::render('EquipmentFiles/Edit', [
            'file'           => $expediente,
            'entities'       => Entity::active()->with('departments:id,entity_id,name,code')
                ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, request()->user()))
                ->get(['id', 'name', 'code']),
            'componentTypes' => ComponentType::active()->orderBy('name')->get(['id', 'slug', 'name', 'category']),
            'statuses'       => Status::active()->orderBy('order')->get(['id', 'name', 'color']),
            'rodas_lookup_enabled' => (bool) ($extDb->enabled && $extDb->host),
            'ldap_responsible_search_enabled' => (bool) ($ldap->enabled && $ldap->host),
        ]);
    }

    public function update(UpdateEquipmentFileRequest $request, EquipmentFile $expediente): RedirectResponse
    {
        $file = $this->service->update($expediente, $request->validated());

        return redirect()
            ->route('expedientes.show', $file)
            ->with('success', 'Expediente actualizado correctamente.');
    }

    public function destroy(EquipmentFile $expediente): RedirectResponse
    {
        $this->authorize('delete', $expediente);
        $this->service->delete($expediente);

        return redirect()
            ->route('expedientes.index')
            ->with('success', 'Expediente eliminado correctamente.');
    }

    public function move(MoveEquipmentRequest $request, EquipmentFile $expediente): RedirectResponse
    {
        $this->authorize('move', $expediente);

        $this->movementService->move(
            $expediente,
            $request->validated('to_entity_id'),
            $request->validated('to_department_id'),
        );

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('success', 'Equipo movido correctamente.');
    }

    public function search(Request $request): JsonResponse
    {
        $results = $this->service->search(
            $request->get('text', ''),
            $request->get('field', 'inventario'),
            $request->user(),
        );

        return response()->json($results);
    }

    public function statistics(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->statistics(
                $request->get('entity_id'),
                $request->get('department_id'),
                $request->user(),
            )
        );
    }

    public function export(Request $request): HttpResponse
    {
        $rows = EquipmentFile::query()
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id'))
            ->with(['entity:id,name', 'department:id,name'])
            ->search($request->get('search'))
            ->filterEntity($request->get('entity_id'))
            ->filterDepartment($request->get('department_id'))
            ->filterStatus($request->get('status'))
            ->orderByDesc('created_at')
            ->get();

        $headers = ['No. expediente', 'Tipo', 'Inventario', 'Entidad', 'Departamento', 'Responsable', 'Estado', 'Fecha'];
        $data = $rows->map(fn ($row) => [
            $row->file_number,
            $row->type,
            $row->inventory_number,
            $row->entity?->name,
            $row->department?->name,
            $row->responsible,
            $row->status,
            optional($row->created_at)->format('Y-m-d H:i:s'),
        ])->all();

        return TabularExport::download(
            (string) $request->get('format', 'csv'),
            'Expedientes tecnicos',
            $headers,
            $data,
            'expedientes'
        );
    }
}
