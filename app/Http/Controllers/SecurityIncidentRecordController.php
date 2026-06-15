<?php

namespace App\Http\Controllers;

use App\Models\EquipmentFile;
use App\Models\SecurityIncidentRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityIncidentRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $records = SecurityIncidentRecord::query()
            ->with('equipmentFile:id,file_number,inventory_number')
            ->when($request->equipment_file_id, fn($q, $id) => $q->where('equipment_file_id', $id))
            ->orderByDesc('incident_date')
            ->orderByDesc('incident_time')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('SecurityIncidentRecords/Index', [
            'records' => $records,
            'filters' => $request->only('equipment_file_id'),
            'files' => EquipmentFile::orderByDesc('id')->get(['id', 'file_number', 'inventory_number']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SecurityIncidentRecord::create($this->validateData($request));
        return back()->with('success', 'Incidencia de seguridad registrada correctamente.');
    }

    public function update(Request $request, SecurityIncidentRecord $incidencias_seguridad): RedirectResponse
    {
        $incidencias_seguridad->update($this->validateData($request));
        return back()->with('success', 'Incidencia de seguridad actualizada correctamente.');
    }

    public function destroy(SecurityIncidentRecord $incidencias_seguridad): RedirectResponse
    {
        $incidencias_seguridad->delete();
        return back()->with('success', 'Incidencia de seguridad eliminada correctamente.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'equipment_file_id' => 'required|exists:expedientes_equipos,id',
            'incident_date' => 'required|date',
            'incident_time' => 'nullable',
            'area' => 'nullable|string|max:255',
            'consecutive_number' => 'nullable|string|max:255',
            'detected_fact' => 'required|string|max:4000',
            'detection_method' => 'nullable|string|max:2000',
            'measures_taken' => 'nullable|string|max:2000',
            'observations' => 'nullable|string|max:2000',
        ]);
    }
}
