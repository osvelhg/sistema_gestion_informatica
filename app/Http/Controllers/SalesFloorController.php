<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterModel;
use App\Models\Entity;
use App\Models\EstablishmentStatus;
use App\Models\EstablishmentType;
use App\Models\NetworkType;
use App\Models\SalesFloor;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesFloorController extends Controller
{
    public function index(Request $request): Response
    {
        $floors = SalesFloor::query()
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id'))
            ->with([
                'municipio:id,name',
                'entity:id,name',
                'cashRegisterModels:id,code,name',
                'areasVenta:id,sales_floor_id,name,tpv_boxes,pos_phone_qty,pos_ip_qty,pos_gprs_qty',
                'networkType:id,name,color',
                'establishmentType:id,name',
                'establishmentStatus:id,name',
            ])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $mapPoints = SalesFloor::query()
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with([
                'entity:id,name,code',
                'networkType:id,name,color',
                'establishmentStatus:id,name',
                'establishmentType:id,name',
            ])
            ->get(['id', 'name', 'address', 'latitude', 'longitude', 'network_type_id', 'establishment_status_id', 'establishment_type_id', 'entity_id']);

        return Inertia::render('SalesFloors/Index', [
            'floors'               => $floors,
            'filters'              => $request->only('search'),
            'entities'             => Entity::active()->with('municipio:id,name')->orderBy('name')
                ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, $request->user()))
                ->get(['id', 'code', 'name', 'municipio_id']),
            'cashRegisterModels'   => CashRegisterModel::active()->orderBy('code')->get(['id', 'code', 'name']),
            'networkTypes'         => NetworkType::active()->orderBy('name')->get(['id', 'name', 'color']),
            'establishmentTypes'   => EstablishmentType::active()->orderBy('name')->get(['id', 'name']),
            'establishmentStatuses'=> EstablishmentStatus::active()->orderBy('name')->get(['id', 'name']),
            'mapPoints'            => $mapPoints,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $entityCodeLabels = $request->boolean('entity_code_labels');

        return response()->json([
            'floors' => SalesFloor::searchForAutocomplete($request->get('q'), 30, $entityCodeLabels, $request->user())->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $floor = SalesFloor::create($data);
        $floor->cashRegisterModels()->sync($this->cashRegisterSyncData($data['cash_registers'] ?? []));

        return back()->with('success', 'Piso de venta creado.');
    }

    public function update(Request $request, SalesFloor $pisoVenta): RedirectResponse
    {
        $data = $this->validateData($request);
        $pisoVenta->update($data);
        $pisoVenta->cashRegisterModels()->sync($this->cashRegisterSyncData($data['cash_registers'] ?? []));

        return back()->with('success', 'Piso de venta actualizado.');
    }

    public function destroy(SalesFloor $pisoVenta): RedirectResponse
    {
        $pisoVenta->delete();
        return back()->with('success', 'Piso de venta eliminado.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'entity_id'              => 'nullable|exists:entidades,id',
            'name'                   => 'required|string|max:255',
            'address'                => 'nullable|string|max:255',
            'phone'                  => 'nullable|string|max:50',
            'active'                 => 'boolean',
            'network_type_id'         => 'nullable|exists:tipos_red,id',
            'establishment_type_id'   => 'nullable|exists:tipos_establecimiento,id',
            'establishment_status_id' => 'nullable|exists:estados_establecimiento,id',
            'latitude'               => 'nullable|string|max:25',
            'longitude'              => 'nullable|string|max:25',
            'codigo_golden'          => 'nullable|string|max:64',
            'almacen_golden'         => 'nullable|string|max:255',
            'cash_registers'         => 'array',
            'cash_registers.*.model_id' => 'required|exists:modelos_caja,id',
            'cash_registers.*.quantity' => 'required|integer|min:0',
        ]);

        $data['municipio_code'] = !empty($data['entity_id'])
            ? Entity::query()->with('municipio:id,code')->find($data['entity_id'])?->municipio?->code
            : null;

        return $data;
    }

    private function cashRegisterSyncData(array $rows): array
    {
        $sync = [];
        foreach ($rows as $row) {
            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty > 0) {
                $sync[(int) $row['model_id']] = ['quantity' => $qty];
            }
        }
        return $sync;
    }
}
