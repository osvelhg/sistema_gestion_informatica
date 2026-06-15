<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Entity;
use App\Models\User;
use App\Support\TabularExport;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $departments = Department::query()
            ->with('entity:id,name,code')
            ->withCount('equipmentFiles')
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user()))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('departamentos.name', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.code', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.telefono', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.codigo_area', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.codigo_entidad', 'ilike', "%{$search}%")
                        ->orWhereHas('entity', function ($eq) use ($search) {
                            $eq->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($request->entity_id, fn ($q, $id) => $q->where('entity_id', $id))
            ->orderBy('departamentos.name')
            ->paginate($this->perPage($request, 15))
            ->withQueryString();

        $entities = Entity::query()
            ->active()
            ->orderBy('name')
            ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, $request->user()))
            ->get(['id', 'name', 'code']);

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'entities' => $entities,
            'filters' => $request->only('search', 'entity_id', 'per_page'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_id' => 'required|exists:entidades,id',
            'name' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:32',
            'code' => 'required|string|max:20',
            'codigo_area' => 'nullable|string|max:50',
            'codigo_entidad' => 'nullable|string|max:20',
            'active' => 'boolean',
        ]);

        $this->assertEntityAllowedForUser($request->user(), (int) $data['entity_id']);

        Department::create($data);

        return back()->with('success', 'Departamento creado.');
    }

    public function update(Request $request, Department $departamento): RedirectResponse
    {
        $this->assertEntityAllowedForUser($request->user(), (int) $departamento->entity_id);

        $data = $request->validate([
            'entity_id' => 'required|exists:entidades,id',
            'name' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:32',
            'code' => 'required|string|max:20',
            'codigo_area' => 'nullable|string|max:50',
            'codigo_entidad' => 'nullable|string|max:20',
            'active' => 'boolean',
        ]);

        $this->assertEntityAllowedForUser($request->user(), (int) $data['entity_id']);

        $departamento->update($data);

        return back()->with('success', 'Departamento actualizado.');
    }

    public function destroy(Request $request, Department $departamento): RedirectResponse
    {
        $this->assertEntityAllowedForUser($request->user(), (int) $departamento->entity_id);

        $departamento->delete();

        return back()->with('success', 'Departamento eliminado.');
    }

    public function byEntity(Entity $entity): JsonResponse
    {
        return response()->json(
            $entity->departments()->active()->get(['id', 'name', 'code'])
        );
    }

    public function export(Request $request): HttpResponse
    {
        $rows = Department::query()
            ->with('entity:id,name,code')
            ->withCount('equipmentFiles')
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user()))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('departamentos.name', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.code', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.telefono', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.codigo_area', 'ilike', "%{$search}%")
                        ->orWhere('departamentos.codigo_entidad', 'ilike', "%{$search}%")
                        ->orWhereHas('entity', function ($eq) use ($search) {
                            $eq->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($request->entity_id, fn ($q, $id) => $q->where('entity_id', $id))
            ->orderBy('departamentos.name')
            ->get();

        $headers = [
            'Entidad',
            'Cód. entidad (tabla)',
            'Nombre',
            'Código',
            'Teléfono',
            'Cód. área (RODAS)',
            'Cód. entidad (RODAS)',
            'Activo',
            'Expedientes',
            'Creado',
            'Actualizado',
        ];

        $data = $rows->map(fn ($row) => [
            $row->entity?->name,
            $row->entity?->code,
            $row->name,
            $row->code,
            $row->telefono,
            $row->codigo_area,
            $row->codigo_entidad,
            $row->active ? 'Sí' : 'No',
            $row->equipment_files_count,
            $row->created_at?->format('d/m/Y H:i'),
            $row->updated_at?->format('d/m/Y H:i'),
        ])->all();

        return TabularExport::download(
            (string) $request->get('format', 'csv'),
            'Departamentos',
            $headers,
            $data,
            'departamentos'
        );
    }

    private function assertEntityAllowedForUser(?User $user, int $entityId): void
    {
        $ids = UserEntityAccess::allowedEntityIds($user);
        if ($ids === null) {
            return;
        }
        if ($ids === [] || ! in_array($entityId, $ids, true)) {
            abort(403);
        }
    }
}
