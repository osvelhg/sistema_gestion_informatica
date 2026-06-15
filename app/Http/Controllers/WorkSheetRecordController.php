<?php

namespace App\Http\Controllers;

use App\Models\EquipmentFile;
use App\Models\InspectionRecord;
use App\Models\WorkSheetRecord;
use App\Models\WorksheetAspect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkSheetRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $records = WorkSheetRecord::query()
            ->with(['equipmentFile:id,file_number,inventory_number', 'inspectionRecord:id,inspection_date'])
            ->when($request->equipment_file_id, fn ($q, $id) => $q->where('equipment_file_id', $id))
            ->orderByDesc('work_date')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('WorkSheetRecords/Index', [
            'records'     => $records,
            'filters'     => $request->only('equipment_file_id'),
            'files'       => EquipmentFile::with('department:id,name')->orderByDesc('id')->get(['id', 'file_number', 'inventory_number', 'department_id']),
            'inspections' => InspectionRecord::orderByDesc('inspection_date')->get(['id', 'equipment_file_id', 'inspection_date']),
            'aspects'     => WorksheetAspect::active()->ordered()->get(['id', 'section', 'label', 'order']),
            'sections'    => [
                'equipamiento' => 'Revisión de Equipamiento',
                'software'     => 'Revisión de Software y Sistemas',
                'salvas'       => 'Salvas',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        WorkSheetRecord::create($this->validateData($request));
        return back()->with('success', 'Hoja de trabajo registrada correctamente.');
    }

    public function update(Request $request, WorkSheetRecord $hojas_trabajo): RedirectResponse
    {
        $hojas_trabajo->update($this->validateData($request));
        return back()->with('success', 'Hoja de trabajo actualizada correctamente.');
    }

    public function destroy(WorkSheetRecord $hojas_trabajo): RedirectResponse
    {
        $hojas_trabajo->delete();
        return back()->with('success', 'Hoja de trabajo eliminada correctamente.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'equipment_file_id'    => 'required|exists:expedientes_equipos,id',
            'inspection_record_id' => 'nullable|exists:inspection_records,id',
            'work_date'            => 'required|date',
            'worksheet_number'     => 'nullable|string|max:255',
            'control_area'         => 'nullable|string|max:255',
            'controlled_area'      => 'nullable|string|max:255',
            'control_action_type'  => 'nullable|string|max:255',
            'started_at'           => 'nullable',
            'ended_at'             => 'nullable',
            'checklist'            => 'nullable|array',
            'checklist.*'          => 'nullable|in:B,R,M',
            'preliminary_results'  => 'nullable|string|max:4000',
            'observations'         => 'nullable|string|max:4000',
            'controller_name'      => 'nullable|string|max:255',
            'controlled_name'      => 'nullable|string|max:255',
        ]);

        // Área controladora es siempre Informática — no se expone en el formulario
        $data['control_area'] = 'Departamento de Informática';

        // Ensure checklist keys are valid aspect IDs
        if (!empty($data['checklist'])) {
            $validIds = WorksheetAspect::pluck('id')->map(fn ($id) => (string) $id)->all();
            $data['checklist'] = array_filter(
                $data['checklist'],
                fn ($key) => in_array((string) $key, $validIds),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return $data;
    }
}
