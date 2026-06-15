<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Entity;
use App\Models\EquipmentFile;
use App\Models\IncidentType;
use App\Models\SupportReport;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SupportReportController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $reports = SupportReport::query()
            ->with([
                'entity:id,name',
                'department:id,name',
                'equipmentFile:id,file_number,inventory_number',
                'incidentType:id,name',
                'creator:id,name',
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('ticket_number', 'ilike', "%{$search}%")
                        ->orWhere('title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('reported_by', 'ilike', "%{$search}%");
                });
            })
            ->when($request->entity_id, fn($query, $entityId) => $query->where('entity_id', $entityId))
            ->when($request->status, fn($query, $status) => $query->where('status', $status))
            ->when($request->incident_type_id, fn($query, $incidentTypeId) => $query->where('incident_type_id', $incidentTypeId))
            ->orderByRaw("CASE WHEN status = 'Abierto' THEN 0 WHEN status = 'En progreso' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => $request->only('search', 'entity_id', 'status', 'incident_type_id'),
            'entities' => Entity::active()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::active()->orderBy('name')->get(['id', 'entity_id', 'name']),
            'files' => EquipmentFile::query()->orderByDesc('id')->get(['id', 'entity_id', 'department_id', 'file_number', 'inventory_number']),
            'incidentTypes' => IncidentType::active()->orderBy('name')->get(['id', 'name']),
            'statuses' => ['Abierto', 'En progreso', 'Cerrado'],
            'priorities' => ['Baja', 'Media', 'Alta', 'Critica'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = Auth::id();
        SupportReport::create($data);

        return back()->with('success', 'Reporte registrado correctamente.');
    }

    public function update(Request $request, SupportReport $reporte): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['closed_at'] = $data['status'] === 'Cerrado' ? now() : null;

        $reporte->update($data);

        return back()->with('success', 'Reporte actualizado correctamente.');
    }

    public function destroy(SupportReport $reporte): RedirectResponse
    {
        $reporte->delete();

        return back()->with('success', 'Reporte eliminado correctamente.');
    }

    public function export(Request $request): HttpResponse
    {
        $rows = SupportReport::query()
            ->with([
                'entity:id,name',
                'department:id,name',
                'equipmentFile:id,file_number,inventory_number',
                'incidentType:id,name',
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('ticket_number', 'ilike', "%{$search}%")
                        ->orWhere('title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('reported_by', 'ilike', "%{$search}%");
                });
            })
            ->when($request->entity_id, fn ($query, $entityId) => $query->where('entity_id', $entityId))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->incident_type_id, fn ($query, $incidentTypeId) => $query->where('incident_type_id', $incidentTypeId))
            ->when($request->priority, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->date_from, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($request->date_to, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->get();

        $headers = ['Ticket', 'Titulo', 'Entidad', 'Departamento', 'Expediente', 'Incidencia', 'Reportado por', 'Estado', 'Prioridad', 'Fecha'];
        $data = $rows->map(fn ($row) => [
            $row->ticket_number,
            $row->title,
            $row->entity?->name,
            $row->department?->name,
            $row->equipmentFile?->file_number,
            $row->incidentType?->name,
            $row->reported_by,
            $row->status,
            $row->priority,
            optional($row->created_at)->format('Y-m-d H:i:s'),
        ])->all();

        return TabularExport::download(
            (string) $request->get('format', 'csv'),
            'Tickets de soporte',
            $headers,
            $data,
            'reportes'
        );
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'entity_id' => 'required|exists:entidades,id',
            'department_id' => 'nullable|exists:departamentos,id',
            'equipment_file_id' => 'nullable|exists:expedientes_equipos,id',
            'incident_type_id' => 'nullable|exists:tipos_incidentes,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:4000',
            'reported_by' => 'required|string|max:255',
            'status' => 'required|in:Abierto,En progreso,Cerrado',
            'priority' => 'required|in:Baja,Media,Alta,Critica',
        ]);
    }
}
