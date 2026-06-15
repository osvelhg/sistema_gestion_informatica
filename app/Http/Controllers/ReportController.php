<?php

namespace App\Http\Controllers;

use App\Models\EquipmentFile;
use App\Models\Entity;
use App\Models\IncidentType;
use App\Models\Seal;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $reports = Seal::query()
            ->with(['entity:id,name', 'equipmentFile:id,file_number,inventory_number', 'incidentType:id,name'])
            ->when($request->entity_id, fn($query, $entityId) => $query->where('entity_id', $entityId))
            ->when($request->incident_type_id, fn($query, $incidentTypeId) => $query->where('incident_type_id', $incidentTypeId))
            ->when($request->date_from, fn($query, $dateFrom) => $query->whereDate('date', '>=', $dateFrom))
            ->when($request->date_to, fn($query, $dateTo) => $query->whereDate('date', '<=', $dateTo))
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => $request->only('entity_id', 'incident_type_id', 'date_from', 'date_to'),
            'entities' => Entity::active()->orderBy('name')->get(['id', 'name']),
            'incidentTypes' => IncidentType::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function expedientePdf(EquipmentFile $expediente): Response
    {
        $expediente->load([
            'entity', 'department', 'creator',
            'components.componentType',
            'seals.incidentType',
            'movements.fromDepartment:id,name',
            'movements.toDepartment:id,name',
            'inspectionRecords',
            'supportControlRecords',
            'responsibles',
        ]);

        $componentes     = $expediente->components->sortBy([['category', 'asc'], ['type', 'asc']])->values();
        $caracteristicas = $componentes->where('category', 'caracteristica')->values();
        $perifericos     = $componentes->where('category', 'periferico')->values();
        $dispositivos    = $componentes->where('category', 'dispositivo')->values();

        $movimientos    = $expediente->movements->sortByDesc('moved_at')->values();
        $inspecciones   = $expediente->inspectionRecords->sortByDesc('inspection_date')->take(5)->values();
        $controlSoporte = $expediente->supportControlRecords->sortByDesc('record_date')->take(5)->values();

        $branding            = SystemSetting::branding();
        $brandingLogoDataUrl = SystemSetting::logoDataUrl();

        $pdf = Pdf::loadView('pdf.expediente', compact(
            'expediente', 'componentes', 'caracteristicas', 'perifericos', 'dispositivos',
            'movimientos', 'inspecciones', 'controlSoporte',
            'branding', 'brandingLogoDataUrl'
        ))->setPaper('letter', 'portrait');

        return $pdf->stream("Expediente_{$expediente->file_number}.pdf");
    }
}
