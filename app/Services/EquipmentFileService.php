<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Department;
use App\Models\Entity;
use App\Models\EquipmentFile;
use App\Models\User;
use App\Models\EquipmentFileResponsible;
use App\Models\ExpedienteAlerta;
use App\Models\ExternalEntityDbSetting;
use App\Models\Seal;
use App\Models\Trabajador;
use App\Support\UserEntityAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EquipmentFileService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data): EquipmentFile
    {
        return DB::transaction(function () use ($data) {
            $caracteristicas = $data['caracteristicas'] ?? [];
            $perifericos = $data['perifericos'] ?? [];
            $dispositivos = $data['dispositivos'] ?? [];
            $sealCode = $data['seal_code'] ?? null;
            $responsibles = $data['responsibles'] ?? [];
            unset(
                $data['caracteristicas'],
                $data['perifericos'],
                $data['dispositivos'],
                $data['components'],
                $data['responsibles'],
            );

            $data['responsible'] = $this->responsibleStringFromRows($responsibles);
            $data['created_by'] = Auth::id();
            $file = EquipmentFile::create($data);

            $this->syncResponsibles($file, $responsibles);
            $this->syncComponents($file, $caracteristicas, $perifericos, $dispositivos);

            if ($sealCode) {
                $now = Carbon::now();

                Seal::create([
                    'entity_id' => $file->entity_id,
                    'equipment_file_id' => $file->id,
                    'inventory_number' => $file->inventory_number,
                    'code' => $sealCode,
                    'applied_seal' => $sealCode,
                    'reason' => 'Creacion de expediente en el nuevo sistema',
                    'date' => $now->toDateString(),
                    'time' => $now->format('H:i:s'),
                    'performed_by' => Auth::user()?->name ?? 'Sistema',
                ]);
            }

            $this->audit->log('CREAR', "Expediente {$file->file_number} creado. Inventario: {$file->inventory_number}", $file);

            $this->createRodasAlertsForMainInventory($file);
            $this->createRodasAlertsForMediosBasicos($file->fresh());

            return $file->load(['components', 'responsibles']);
        });
    }

    public function update(EquipmentFile $file, array $data): EquipmentFile
    {
        return DB::transaction(function () use ($file, $data) {
            $caracteristicas = $data['caracteristicas'] ?? [];
            $perifericos = $data['perifericos'] ?? [];
            $dispositivos = $data['dispositivos'] ?? [];
            $responsibles = $data['responsibles'] ?? null;
            unset($data['caracteristicas'], $data['perifericos'], $data['dispositivos'], $data['components'], $data['responsibles']);

            if ($responsibles !== null) {
                $data['responsible'] = $this->responsibleStringFromRows($responsibles);
            }

            $file->update($data);

            if ($responsibles !== null) {
                $this->syncResponsibles($file->fresh(), $responsibles);
            }

            $this->syncComponents($file->fresh(), $caracteristicas, $perifericos, $dispositivos);

            $this->audit->log('MODIFICAR', "Expediente {$file->file_number} actualizado.", $file);

            ExpedienteAlerta::query()
                ->where('equipment_file_id', $file->id)
                ->whereIn('type', [
                    ExpedienteAlerta::TYPE_RODAS_MEDIO_INVENTARIO_INEXISTENTE,
                    ExpedienteAlerta::TYPE_RODAS_MEDIO_INCONGRUENCIA,
                ])
                ->delete();

            $this->createRodasAlertsForMediosBasicos($file->fresh());

            return $file->fresh(['components', 'responsibles']);
        });
    }

    public function delete(EquipmentFile $file): void
    {
        $this->audit->log('ELIMINAR', "Expediente {$file->file_number} eliminado. Inventario: {$file->inventory_number}", $file);
        $file->delete();
    }

    /**
     * @param  array<int, array{display_name?: string, samaccountname?: string|null, mail?: string|null, source?: string}>  $rows
     */
    private function responsibleStringFromRows(array $rows): string
    {
        return collect($rows)
            ->pluck('display_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->implode(', ');
    }

    /**
     * @param  array<int, array{display_name?: string, samaccountname?: string|null, mail?: string|null, source?: string, trabajador_id?: int|null}>  $responsibles
     */
    private function syncResponsibles(EquipmentFile $file, array $responsibles): void
    {
        EquipmentFileResponsible::query()
            ->where('equipment_file_id', $file->id)
            ->delete();

        ExpedienteAlerta::query()
            ->where('equipment_file_id', $file->id)
            ->where('type', ExpedienteAlerta::TYPE_RESPONSIBLE_MANUAL)
            ->delete();

        foreach (array_values($responsibles) as $i => $row) {
            $display = trim((string) ($row['display_name'] ?? ''));
            if ($display === '') {
                continue;
            }

            $source = (($row['source'] ?? 'manual') === 'ad') ? 'ad' : 'manual';
            $sam = isset($row['samaccountname']) ? (trim((string) $row['samaccountname']) ?: null) : null;
            $mail = isset($row['mail']) ? (trim((string) $row['mail']) ?: null) : null;

            // Resolver trabajador_id: usar el proporcionado o buscar/crear automáticamente
            $trabajadorId = $row['trabajador_id'] ?? null;

            if (! $trabajadorId && $source === 'ad' && $sam) {
                $trabajador = Trabajador::findExistingMatch(null, $sam, $display);

                if (! $trabajador) {
                    $trabajador = Trabajador::create([
                        'nombre'         => $display,
                        'samaccountname' => $sam,
                        'email'          => $mail,
                        'origen'         => 'active_directory',
                        'estado'         => true,
                    ]);
                }

                $trabajadorId = $trabajador->id;
            }

            EquipmentFileResponsible::create([
                'equipment_file_id' => $file->id,
                'trabajador_id'       => $trabajadorId,
                'display_name'        => $display,
                'samaccountname'      => $sam,
                'mail'                => $mail,
                'source'              => $source,
                'sort_order'          => $i,
            ]);

            if ($source === 'manual') {
                ExpedienteAlerta::create([
                    'equipment_file_id' => $file->id,
                    'type'                => ExpedienteAlerta::TYPE_RESPONSIBLE_MANUAL,
                    'message'             => 'Responsable sin verificación en Active Directory: '.$display,
                    'meta'                => [
                        'display_name'   => $display,
                        'samaccountname' => $row['samaccountname'] ?? null,
                    ],
                ]);
            }
        }
    }

    private function createRodasAlertsForMainInventory(EquipmentFile $file): void
    {
        $cfg = ExternalEntityDbSetting::current();
        if (! $cfg->enabled || ! $cfg->host) {
            return;
        }

        $entity = Entity::find($file->entity_id);
        if (! $entity) {
            return;
        }

        $inv = trim((string) $file->inventory_number);

        $rodas = null;
        $lookupError = null;
        try {
            $rodas = ExternalEntityDbService::lookupActivoForEntity($cfg, $entity, $inv);
        } catch (\Throwable $e) {
            $lookupError = $e;
        } finally {
            ExternalEntityDbService::purgeDynamicConnection();
        }

        if ($lookupError !== null) {
            ExpedienteAlerta::create([
                'equipment_file_id' => $file->id,
                'type'              => ExpedienteAlerta::TYPE_RODAS_INVENTARIO_INEXISTENTE,
                'message'           => 'No se pudo verificar el inventario en RODAS: '.$lookupError->getMessage(),
                'meta'              => ['inventory_number' => $inv, 'entity_id' => $file->entity_id],
            ]);

            return;
        }

        if (! $rodas['found']) {
            ExpedienteAlerta::create([
                'equipment_file_id' => $file->id,
                'type'              => ExpedienteAlerta::TYPE_RODAS_INVENTARIO_INEXISTENTE,
                'message'           => 'El inventario no existe en la tabla de activos de RODAS para la entidad seleccionada. Se permitió el alta con datos manuales; revise la coherencia con el servidor contable.',
                'meta'              => [
                    'inventory_number' => $inv,
                    'entity_id'        => $file->entity_id,
                ],
            ]);

            return;
        }

        $dept = Department::find($file->department_id);
        $codigoSeleccionado = $dept ? trim((string) $dept->codigo_area) : '';
        $codigoRodas = trim((string) ($rodas['codigo_area'] ?? ''));
        $deptIdRodas = $rodas['department_id'] ?? null;

        $areaCoincide = $codigoRodas !== ''
            && $codigoSeleccionado !== ''
            && $codigoRodas === $codigoSeleccionado;

        $deptoIdCoincide = $deptIdRodas === null
            || (int) $file->department_id === (int) $deptIdRodas;

        if (! $areaCoincide || ! $deptoIdCoincide) {
            ExpedienteAlerta::create([
                'equipment_file_id' => $file->id,
                'type'              => ExpedienteAlerta::TYPE_RODAS_INCONGRUENCIA,
                'message'           => 'La entidad, el departamento o el inventario no coinciden con el registro en RODAS (área de responsabilidad / activos).',
                'meta'              => [
                    'inventory_number'         => $inv,
                    'entity_id'                => $file->entity_id,
                    'department_id'            => $file->department_id,
                    'codigo_area_rodas'        => $codigoRodas,
                    'codigo_area_seleccionado' => $codigoSeleccionado,
                    'department_id_rodas'      => $deptIdRodas,
                ],
            ]);
        }
    }

    /**
     * Periféricos y dispositivos: el inventario debe existir en activos (RODAS) y el área coincidir con el departamento del expediente.
     */
    private function createRodasAlertsForMediosBasicos(EquipmentFile $file): void
    {
        $cfg = ExternalEntityDbSetting::current();
        if (! $cfg->enabled || ! $cfg->host) {
            return;
        }

        $entity = Entity::find($file->entity_id);
        if (! $entity) {
            return;
        }

        $medios = Component::query()
            ->where('equipment_file_id', $file->id)
            ->whereIn('category', ['periferico', 'dispositivo'])
            ->with('componentType')
            ->orderBy('id')
            ->get();

        $deptExpediente = Department::find($file->department_id);
        $codigoDeptExpediente = $deptExpediente ? trim((string) $deptExpediente->codigo_area) : '';

        foreach ($medios as $component) {
            $inv = trim((string) ($component->inventory_number ?? ''));
            if ($inv === '') {
                continue;
            }

            $etiqueta = $component->label;
            $categoria = $component->category === 'periferico' ? 'Periférico' : 'Otro dispositivo';

            $rodas = null;
            $lookupError = null;
            try {
                $rodas = ExternalEntityDbService::lookupActivoForEntity($cfg, $entity, $inv);
            } catch (\Throwable $e) {
                $lookupError = $e;
            } finally {
                ExternalEntityDbService::purgeDynamicConnection();
            }

            if ($lookupError !== null) {
                ExpedienteAlerta::create([
                    'equipment_file_id' => $file->id,
                    'type'              => ExpedienteAlerta::TYPE_RODAS_MEDIO_INVENTARIO_INEXISTENTE,
                    'message'           => "{$categoria} «{$etiqueta}» (inv. {$inv}): no se pudo verificar en RODAS — ".$lookupError->getMessage(),
                    'meta'              => [
                        'scope'               => 'medio_basico',
                        'category'            => $component->category,
                        'component_type'      => $component->type,
                        'inventory_number'    => $inv,
                        'entity_id'           => $file->entity_id,
                    ],
                ]);

                continue;
            }

            if (! $rodas['found']) {
                ExpedienteAlerta::create([
                    'equipment_file_id' => $file->id,
                    'type'              => ExpedienteAlerta::TYPE_RODAS_MEDIO_INVENTARIO_INEXISTENTE,
                    'message'           => "{$categoria} «{$etiqueta}» (inv. {$inv}): no consta en la tabla de activos de RODAS para esta entidad. Revise el inventario conforme a la normativa de medios básicos.",
                    'meta'              => [
                        'scope'            => 'medio_basico',
                        'category'         => $component->category,
                        'component_type'   => $component->type,
                        'inventory_number' => $inv,
                        'entity_id'        => $file->entity_id,
                    ],
                ]);

                continue;
            }

            $codigoRodas = trim((string) ($rodas['codigo_area'] ?? ''));
            $deptIdRodas = $rodas['department_id'] ?? null;

            $areaCoincide = $codigoRodas !== ''
                && $codigoDeptExpediente !== ''
                && $codigoRodas === $codigoDeptExpediente;

            $deptoIdCoincide = $deptIdRodas === null
                || (int) $file->department_id === (int) $deptIdRodas;

            if (! $areaCoincide || ! $deptoIdCoincide) {
                ExpedienteAlerta::create([
                    'equipment_file_id' => $file->id,
                    'type'              => ExpedienteAlerta::TYPE_RODAS_MEDIO_INCONGRUENCIA,
                    'message'           => "{$categoria} «{$etiqueta}» (inv. {$inv}): el área de responsabilidad en RODAS no coincide con el departamento del expediente.",
                    'meta'              => [
                        'scope'                    => 'medio_basico',
                        'category'                 => $component->category,
                        'component_type'           => $component->type,
                        'inventory_number'         => $inv,
                        'entity_id'                => $file->entity_id,
                        'department_id'            => $file->department_id,
                        'codigo_area_rodas'        => $codigoRodas,
                        'codigo_area_expediente'   => $codigoDeptExpediente,
                        'department_id_rodas'      => $deptIdRodas,
                    ],
                ]);
            }
        }
    }

    public function statistics(?int $entityId = null, ?int $departmentId = null, ?User $user = null): array
    {
        $query = EquipmentFile::query();
        if ($user) {
            UserEntityAccess::whereEntityIdAllowed($query, $user, 'entity_id');
        }
        $query->filterEntity($entityId)
            ->filterDepartment($departmentId);

        return [
            'bien' => (clone $query)->where('status', 'Bien')->count(),
            'regular' => (clone $query)->where('status', 'Regular')->count(),
            'mal' => (clone $query)->where('status', 'Mal')->count(),
            'total' => (clone $query)->count(),
        ];
    }

    public function search(string $text, string $field, ?User $user = null): \Illuminate\Support\Collection
    {
        $columnMap = [
            'inventario' => ['inventory_number'],
            'serie' => ['serial_number'],
            'marca' => ['brand'],
            'modelo' => ['model'],
        ];

        $columns = $columnMap[$field] ?? ['serial_number'];

        return Component::query()
            ->whereHas('equipmentFile', function ($eq) use ($user) {
                if ($user) {
                    UserEntityAccess::whereEntityIdAllowed($eq, $user, 'entity_id');
                }
            })
            ->where(function ($query) use ($columns, $text) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'ilike', "%{$text}%");
                }
            })
            ->with('equipmentFile.entity', 'equipmentFile.department')
            ->get()
            ->pluck('equipmentFile')
            ->unique('id')
            ->values();
    }

    private function syncComponents(
        EquipmentFile $file,
        array $caracteristicas,
        array $perifericos = [],
        array $dispositivos = []
    ): void {
        $this->syncDynamicCategory($file, 'caracteristica', $caracteristicas);
        $this->syncDynamicCategory($file, 'periferico', $perifericos);
        $this->syncDynamicCategory($file, 'dispositivo', $dispositivos);
    }

    private function syncDynamicCategory(EquipmentFile $file, string $category, array $items = []): void
    {
        $file->components()->where('category', $category)->delete();

        foreach ($items as $item) {
            if (empty($item['component_type_slug'])) {
                continue;
            }

            Component::create([
                'equipment_file_id' => $file->id,
                'category' => $category,
                'type' => $item['component_type_slug'],
                'brand' => $item['brand'] ?? null,
                'model' => $item['model'] ?? null,
                'inventory_number' => $category === 'caracteristica' ? null : ($item['inventory_number'] ?? null),
                'serial_number' => $item['serial_number'] ?? null,
                'status' => ! empty($item['status']) ? (string) $item['status'] : 'Bien',
            ]);
        }
    }
}
