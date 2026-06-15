<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EquipmentFile;
use App\Models\IncidentType;
use App\Models\Seal;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SealController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $seals = Seal::query()
            ->with(['entity:id,name', 'equipmentFile:id,file_number,inventory_number', 'incidentType:id,name'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('applied_seal', 'ilike', "%{$search}%")
                        ->orWhere('removed_seal', 'ilike', "%{$search}%")
                        ->orWhere('reason', 'ilike', "%{$search}%")
                        ->orWhere('inventory_number', 'ilike', "%{$search}%")
                        ->orWhere('performed_by', 'ilike', "%{$search}%");
                });
            })
            ->when($request->entity_id, fn($query, $entityId) => $query->where('entity_id', $entityId))
            ->when($request->incident_type_id, fn($query, $incidentTypeId) => $query->where('incident_type_id', $incidentTypeId))
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Seals/Index', [
            'seals' => $seals,
            'filters' => $request->only('search', 'entity_id', 'incident_type_id'),
            'entities' => Entity::active()->get(['id', 'name']),
            'files' => EquipmentFile::query()->orderByDesc('id')->get(['id', 'file_number', 'inventory_number', 'entity_id']),
            'incidentTypes' => IncidentType::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $file = EquipmentFile::find($data['equipment_file_id']);

        $data['inventory_number'] = $file?->inventory_number ?? $data['inventory_number'];
        $data['performed_by'] = $data['performed_by'] ?: Auth::user()?->name;
        $data['code'] = $data['applied_seal'] ?: ($data['removed_seal'] ?? null);

        Seal::create($data);

        if ($file && $data['applied_seal']) {
            $file->update(['seal_code' => $data['applied_seal']]);
        }

        return back()->with('success', 'Registro de sello creado correctamente.');
    }

    public function update(Request $request, Seal $sello): RedirectResponse
    {
        $data = $this->validateData($request);
        $file = EquipmentFile::find($data['equipment_file_id']);

        $data['inventory_number'] = $file?->inventory_number ?? $data['inventory_number'];
        $data['performed_by'] = $data['performed_by'] ?: Auth::user()?->name;
        $data['code'] = $data['applied_seal'] ?: ($data['removed_seal'] ?? null);

        $sello->update($data);

        if ($file && $data['applied_seal']) {
            $file->update(['seal_code' => $data['applied_seal']]);
        }

        return back()->with('success', 'Registro de sello actualizado correctamente.');
    }

    public function destroy(Seal $sello): RedirectResponse
    {
        $sello->delete();

        return back()->with('success', 'Registro de sello eliminado correctamente.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'entity_id' => 'required|exists:entidades,id',
            'equipment_file_id' => 'nullable|exists:expedientes_equipos,id',
            'incident_type_id' => 'nullable|exists:tipos_incidentes,id',
            'inventory_number' => 'nullable|string|max:50',
            'removed_seal' => 'nullable|string|max:100',
            'applied_seal' => 'nullable|string|max:100',
            'reason' => 'required|string|max:500',
            'date' => 'required|date',
            'time' => 'required',
            'performed_by' => 'nullable|string|max:255',
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $rows = Seal::query()
            ->with(['entity:id,name', 'equipmentFile:id,file_number,inventory_number', 'incidentType:id,name'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('applied_seal', 'ilike', "%{$search}%")
                        ->orWhere('removed_seal', 'ilike', "%{$search}%")
                        ->orWhere('reason', 'ilike', "%{$search}%")
                        ->orWhere('inventory_number', 'ilike', "%{$search}%")
                        ->orWhere('performed_by', 'ilike', "%{$search}%");
                });
            })
            ->when($request->entity_id, fn ($query, $entityId) => $query->where('entity_id', $entityId))
            ->when($request->incident_type_id, fn ($query, $incidentTypeId) => $query->where('incident_type_id', $incidentTypeId))
            ->when($request->date_from, fn ($query, $dateFrom) => $query->whereDate('date', '>=', $dateFrom))
            ->when($request->date_to, fn ($query, $dateTo) => $query->whereDate('date', '<=', $dateTo))
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->get();

        $headers = ['Entidad', 'Expediente', 'Inventario', 'Incidencia', 'Sello retirado', 'Sello aplicado', 'Motivo', 'Fecha', 'Hora', 'Realizado por'];
        $data = $rows->map(fn ($row) => [
            $row->entity?->name,
            $row->equipmentFile?->file_number,
            $row->inventory_number,
            $row->incidentType?->name,
            $row->removed_seal,
            $row->applied_seal,
            $row->reason,
            $row->date,
            $row->time,
            $row->performed_by,
        ])->all();

        return TabularExport::download(
            (string) $request->get('format', 'csv'),
            'Control de sellos',
            $headers,
            $data,
            'sellos'
        );
    }
}
