<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesAreaVentaFields;
use App\Models\AreaVenta;
use App\Models\Entity;
use App\Models\SalesFloor;
use App\Support\UserEntityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AreaVentaController extends Controller
{
    use ValidatesAreaVentaFields;

    public function index(Request $request): Response
    {
        $areas = AreaVenta::query()
            ->with([
                'salesFloor:id,name,entity_id',
                'salesFloor.entity:id,name,code',
            ])
            ->whereHas('salesFloor', function ($q) use ($request) {
                UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id');
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('areas_venta.name', 'ilike', "%{$search}%")
                        ->orWhereHas('salesFloor', function ($sq) use ($search) {
                            $sq->where('name', 'ilike', "%{$search}%")
                                ->orWhereHas('entity', function ($eq) use ($search) {
                                    $eq->where('name', 'ilike', "%{$search}%")
                                        ->orWhere('code', 'ilike', "%{$search}%");
                                });
                        });
                });
            })
            ->when($request->filled('entity_id'), fn ($q) => $q->whereHas('salesFloor', fn ($sq) => $sq->where('entity_id', (int) $request->entity_id)))
            ->join('pisos_venta', 'pisos_venta.id', '=', 'areas_venta.sales_floor_id')
            ->orderBy('pisos_venta.name')
            ->orderBy('areas_venta.name')
            ->select('areas_venta.*')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        $areas->getCollection()->load([
            'datacellSources' => fn ($q) => $q
                ->with(['canalElectronico:id,nombre'])
                ->orderBy('source_name'),
        ]);

        $floorFilterOptions = SalesFloor::query()
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id'))
            ->with('entity:id,name,code')
            ->orderBy('name')
            ->get(['id', 'name', 'entity_id'])
            ->map(fn (SalesFloor $f) => [
                'id'        => $f->id,
                'entity_id' => $f->entity_id,
                'name'      => $f->name,
                'label'     => $f->entity
                    ? '['.($f->entity->code ?? '—').'] '.$f->entity->name.' · '.$f->name
                    : $f->name,
            ]);

        $entityFilterOptions = Entity::query()
            ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, $request->user()))
            ->orderBy('code')
            ->get(['id', 'name', 'code'])
            ->map(fn (Entity $e) => [
                'id'    => $e->id,
                'name'  => $e->name,
                'code'  => $e->code,
                'label' => '['.($e->code ?? '—').'] '.$e->name,
            ]);

        $importEntityOptions = $entityFilterOptions->map(fn ($e) => ['id' => $e['id'], 'label' => $e['label']]);

        return Inertia::render('AreasVenta/Index', [
            'areas'                => $areas,
            'filters'              => $request->only(['search', 'entity_id']),
            'floorFilterOptions'   => $floorFilterOptions,
            'entityFilterOptions'  => $entityFilterOptions,
            'importEntityOptions'  => $importEntityOptions,
            'cashRegisterModels'  => AreaVenta::CASH_REGISTER_MODELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAreaVentaFields($request);
        $this->assertFloorAllowed($request, (int) $data['sales_floor_id']);
        AreaVenta::create($data);

        return back()->with('success', 'Área de venta creada.');
    }

    public function update(Request $request, AreaVenta $areaVenta): RedirectResponse
    {
        $this->assertAreaAllowed($request, $areaVenta);
        $data = $this->validateAreaVentaFields($request);
        if ((int) $data['sales_floor_id'] !== (int) $areaVenta->sales_floor_id) {
            $this->assertFloorAllowed($request, (int) $data['sales_floor_id']);
        }
        $areaVenta->update($data);

        return back()->with('success', 'Área de venta actualizada.');
    }

    public function destroy(Request $request, AreaVenta $areaVenta): RedirectResponse
    {
        $this->assertAreaAllowed($request, $areaVenta);
        $areaVenta->delete();

        return back()->with('success', 'Área de venta eliminada.');
    }

    private function assertFloorAllowed(Request $request, int $salesFloorId): void
    {
        $floor = SalesFloor::query()->find($salesFloorId);
        $this->assertUserCanAccessFloor($request, $floor);
    }

    private function assertAreaAllowed(Request $request, AreaVenta $area): void
    {
        $area->loadMissing('salesFloor');
        $this->assertUserCanAccessFloor($request, $area->salesFloor);
    }

    private function assertUserCanAccessFloor(Request $request, ?SalesFloor $floor): void
    {
        if (!$floor) {
            abort(404);
        }

        $ids = UserEntityAccess::allowedEntityIds($request->user());
        if ($ids === null) {
            return;
        }

        if ($ids === [] || !in_array((int) $floor->entity_id, array_map('intval', $ids), true)) {
            abort(403);
        }
    }
}
