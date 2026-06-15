<?php

namespace App\Http\Controllers;

use App\Models\EquipmentFile;
use App\Models\SupportControlRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportControlRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $records = SupportControlRecord::query()
            ->with('equipmentFile:id,file_number,inventory_number')
            ->when($request->equipment_file_id, fn($q, $id) => $q->where('equipment_file_id', $id))
            ->orderByDesc('record_date')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('SupportControlRecords/Index', [
            'records' => $records,
            'filters' => $request->only('equipment_file_id'),
            'files' => EquipmentFile::orderByDesc('id')->get(['id', 'file_number', 'inventory_number']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SupportControlRecord::create($this->validateData($request));
        return back()->with('success', 'Control de soporte registrado correctamente.');
    }

    public function update(Request $request, SupportControlRecord $control_soporte): RedirectResponse
    {
        $control_soporte->update($this->validateData($request));
        return back()->with('success', 'Control de soporte actualizado correctamente.');
    }

    public function destroy(SupportControlRecord $control_soporte): RedirectResponse
    {
        $control_soporte->delete();
        return back()->with('success', 'Control de soporte eliminado correctamente.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'equipment_file_id' => 'required|exists:expedientes_equipos,id',
            'record_date' => 'nullable|date',
            'area' => 'nullable|string|max:255',
            'support_number' => 'required|string|max:255',
            'content_summary' => 'nullable|string|max:4000',
            'observations' => 'nullable|string|max:4000',
        ]);
    }
}
