<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Entity;
use App\Models\EquipmentFile;
use App\Models\InspectionRecord;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class InspectionRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $records = InspectionRecord::query()
            ->with(['equipmentFile:id,file_number,inventory_number', 'entity:id,name', 'department:id,name'])
            ->when($request->equipment_file_id, fn($q, $id) => $q->where('equipment_file_id', $id))
            ->orderByDesc('inspection_date')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('InspectionRecords/Index', [
            'records' => $records,
            'filters' => $request->only('equipment_file_id'),
            'files' => EquipmentFile::orderByDesc('id')->get(['id', 'file_number', 'inventory_number', 'entity_id', 'department_id']),
            'entities' => Entity::active()->get(['id', 'name']),
            'departments' => Department::active()->get(['id', 'entity_id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        InspectionRecord::create($this->validateData($request));
        return back()->with('success', 'Inspeccion registrada correctamente.');
    }

    public function update(Request $request, InspectionRecord $inspeccione): RedirectResponse
    {
        $inspeccione->update($this->validateData($request));
        return back()->with('success', 'Inspeccion actualizada correctamente.');
    }

    public function destroy(InspectionRecord $inspeccione): RedirectResponse
    {
        $inspeccione->delete();
        return back()->with('success', 'Inspeccion eliminada correctamente.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'equipment_file_id' => 'required|exists:expedientes_equipos,id',
            'entity_id' => 'required|exists:entidades,id',
            'department_id' => 'nullable|exists:departamentos,id',
            'inspection_date' => 'required|date',
            'participants' => 'nullable|string|max:255',
            'situations_detected' => 'required|string|max:4000',
            'worksheet_reference' => 'nullable|string|max:255',
        ]);
    }
}
