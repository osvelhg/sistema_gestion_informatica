<?php

namespace App\Http\Controllers;

use App\Models\AreaVenta;
use App\Models\AreaVentaFuente;
use App\Models\DatacellSource;
use App\Models\PisoVentaFuente;
use App\Models\SalesFloor;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesFloorAreaQrController extends Controller
{
    public function index(Request $request): Response
    {
        $floors = SalesFloor::query()
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id'))
            ->with([
                'entity:id,name,code',
                'networkType:id,name',
                'pisoDatacellFuentes' => fn ($q) => $q
                    ->with(['canalElectronico:id,nombre'])
                    ->orderBy('source_name')
                    ->orderBy('source'),
                'areasVenta' => fn ($q) => $q->orderBy('name'),
                'areasVenta.datacellSources' => fn ($q) => $q
                    ->with(['canalElectronico:id,nombre'])
                    ->orderBy('source_name')
                    ->orderBy('source'),
            ])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate($this->perPage($request, 20))
            ->withQueryString();

        return Inertia::render('SalesFloors/AreasQr', [
            'floors'  => $floors,
            'filters' => $request->only('search'),
        ]);
    }

    public function searchFuentes(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['fuentes' => []]);
        }

        $fuentes = DatacellSource::query()
            ->with(['canalElectronico:id,nombre', 'areasVenta:id,name'])
            ->where(function ($w) use ($q) {
                $w->where('source', 'ilike', "%{$q}%")
                    ->orWhere('source_name', 'ilike', "%{$q}%");
            })
            ->orderBy('source_name')
            ->orderBy('source')
            ->limit(40)
            ->get(['id', 'source', 'source_name', 'moneda', 'canal_electronico_id', 'activo']);

        return response()->json(['fuentes' => $fuentes]);
    }

    public function storeLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'link_type'      => 'required|in:area,floor',
            'area_venta_id'  => 'required_if:link_type,area|nullable|exists:areas_venta,id',
            'sales_floor_id' => 'required_if:link_type,floor|nullable|exists:pisos_venta,id',
            'fuente_id'      => 'required|exists:fuentes,id',
        ]);

        $fuente = DatacellSource::query()->findOrFail($data['fuente_id']);

        if (!$fuente->activo) {
            return back()->withErrors(['fuente_id' => 'La fuente QR no está activa.']);
        }

        $canalId  = $fuente->canal_electronico_id;
        $canalKey = $canalId ?? 0;

        if ($data['link_type'] === 'area') {
            $area = AreaVenta::query()->with('salesFloor')->findOrFail($data['area_venta_id']);
            $this->assertUserCanAccessFloor($request, $area->salesFloor);

            $existsCanal = AreaVentaFuente::query()
                ->where('area_venta_id', $area->id)
                ->where('canal_key', $canalKey)
                ->exists();

            if ($existsCanal) {
                return back()->withErrors([
                    'fuente_id' => 'Esta área ya tiene una fuente para el mismo canal electrónico.',
                ]);
            }

            AreaVentaFuente::create([
                'area_venta_id'        => $area->id,
                'fuente_id'            => $fuente->id,
                'canal_key'            => $canalKey,
                'canal_electronico_id' => $canalId,
            ]);

            $fuente->refreshSalesFloorIdFromQrPivots();

            return back()->with('success', 'Fuente QR vinculada al área.');
        }

        $floor = SalesFloor::query()->findOrFail($data['sales_floor_id']);
        $this->assertUserCanAccessFloor($request, $floor);

        $existsCanal = PisoVentaFuente::query()
            ->where('sales_floor_id', $floor->id)
            ->where('canal_key', $canalKey)
            ->exists();

        if ($existsCanal) {
            return back()->withErrors([
                'fuente_id' => 'Este piso ya tiene una fuente vinculada para el mismo canal electrónico.',
            ]);
        }

        PisoVentaFuente::create([
            'sales_floor_id'       => $floor->id,
            'fuente_id'            => $fuente->id,
            'canal_key'            => $canalKey,
            'canal_electronico_id' => $canalId,
        ]);

        $fuente->refreshSalesFloorIdFromQrPivots();

        return back()->with('success', 'Fuente QR vinculada al piso de venta.');
    }

    public function destroyAreaLink(Request $request, AreaVentaFuente $vinculo): RedirectResponse
    {
        $area = AreaVenta::query()->with('salesFloor')->findOrFail($vinculo->area_venta_id);
        $this->assertUserCanAccessFloor($request, $area->salesFloor);

        $fuenteId = $vinculo->fuente_id;
        $vinculo->delete();
        DatacellSource::query()->find($fuenteId)?->refreshSalesFloorIdFromQrPivots();

        return back()->with('success', 'Vínculo eliminado.');
    }

    public function destroyPisoLink(Request $request, PisoVentaFuente $vinculo): RedirectResponse
    {
        $floor = SalesFloor::query()->findOrFail($vinculo->sales_floor_id);
        $this->assertUserCanAccessFloor($request, $floor);

        $fuenteId = $vinculo->fuente_id;
        $vinculo->delete();
        DatacellSource::query()->find($fuenteId)?->refreshSalesFloorIdFromQrPivots();

        return back()->with('success', 'Vínculo eliminado.');
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
