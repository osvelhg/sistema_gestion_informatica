<?php

namespace App\Http\Controllers;

use App\Models\EtecsaFactura;
use App\Services\AuditService;
use App\Services\EtecsaImportService;
use App\Services\EtecsaPdfParser;
use App\Support\TabularExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EtecsaFacturaController extends Controller
{
    public function __construct(
        private readonly EtecsaPdfParser    $parser,
        private readonly EtecsaImportService $importService,
        private readonly AuditService        $auditService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $facturas = EtecsaFactura::query()
            ->with('importedBy:id,name')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($w) use ($search) {
                    $w->where('numero_factura', 'ilike', "%{$search}%")
                        ->orWhere('numero_cliente', 'ilike', "%{$search}%")
                        ->orWhere('nombre_cliente', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_factura', $request->tipo))
            ->when($request->filled('periodo_desde'), fn ($q) => $q->where('periodo_desde', '>=', $request->periodo_desde))
            ->when($request->filled('periodo_hasta'), fn ($q) => $q->where('periodo_hasta', '<=', $request->periodo_hasta))
            ->withCount('servicios')
            ->orderByDesc('periodo_desde')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25))
            ->withQueryString();

        return Inertia::render('EtecsaFacturacion/Index', [
            'facturas' => $facturas,
            'filters'  => $request->only(['search', 'tipo', 'periodo_desde', 'periodo_hasta']),
        ]);
    }

    public function show(EtecsaFactura $factura): InertiaResponse
    {
        $factura->load([
            'servicios' => fn ($q) => $q->with([
                'connectivityRecord:id,id_facturacion,tipo_enlace,velocidad_etecsa,cuota,sales_floor_id',
                'connectivityRecord.salesFloor:id,name,entity_id',
                'connectivityRecord.salesFloor.entity:id,name,code',
                'salesFloorDirect:id,name,entity_id',
                'salesFloorDirect.entity:id,name,code',
                'department:id,name,entity_id',
                'department.entity:id,name,code',
            ])->withCount('llamadas'),
            'importedBy:id,name',
        ]);

        return Inertia::render('EtecsaFacturacion/Show', [
            'factura' => $factura,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Importación PDF (preview → apply)
    // ──────────────────────────────────────────────────────────────────────────

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:20480',
        ]);

        try {
            $parsed  = $this->parser->parse($request->file('pdf_file')->getRealPath());
            $preview = $this->importService->buildPreview($parsed, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($preview['duplicate'] ?? false) {
            return response()->json([
                'success'   => false,
                'duplicate' => true,
                'message'   => 'Esta factura ya fue importada anteriormente (PDF duplicado).',
            ], 409);
        }

        return response()->json(['success' => true] + $preview);
    }

    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'preview' => 'required|array',
        ]);

        try {
            $factura = $this->importService->apply($request->input('preview'), $request->user());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'    => true,
            'message'    => "Factura {$factura->numero_factura} importada correctamente.",
            'factura_id' => $factura->id,
            'redirect'   => route('etecsa.show', $factura),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Búsqueda de ConnectivityRecord para vincular manualmente
    // ──────────────────────────────────────────────────────────────────────────

    public function buscarServicio(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $records = \App\Models\ConnectivityRecord::query()
            ->where('id_facturacion', 'ilike', "%{$q}%")
            ->with('salesFloor:id,name')
            ->limit(20)
            ->get(['id', 'id_facturacion', 'tipo_enlace', 'velocidad_etecsa', 'cuota', 'sales_floor_id']);

        return response()->json([
            'results' => $records->map(fn ($r) => [
                'id'               => $r->id,
                'id_facturacion'   => $r->id_facturacion,
                'tipo_enlace'      => $r->tipo_enlace,
                'velocidad_etecsa' => $r->velocidad_etecsa,
                'cuota'            => $r->cuota,
                'sales_floor_name' => $r->salesFloor?->name,
            ])->values()->all(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Eliminación
    // ──────────────────────────────────────────────────────────────────────────

    public function destroy(EtecsaFactura $factura): RedirectResponse
    {
        $numero = $factura->numero_factura;
        $factura->delete();

        $this->auditService->log(
            'etecsa.factura.eliminada',
            "Eliminada factura ETECSA {$numero}",
        );

        return redirect()->route('etecsa.index')
            ->with('success', "Factura {$numero} eliminada.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Exportación
    // ──────────────────────────────────────────────────────────────────────────

    public function export(Request $request): HttpResponse
    {
        $rows = EtecsaFactura::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($w) use ($search) {
                    $w->where('numero_factura', 'ilike', "%{$search}%")
                        ->orWhere('numero_cliente', 'ilike', "%{$search}%")
                        ->orWhere('nombre_cliente', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_factura', $request->tipo))
            ->withCount('servicios')
            ->orderByDesc('periodo_desde')
            ->get();

        $headers = [
            'Factura', 'N° Cliente', 'Cliente', 'Período desde', 'Período hasta',
            'Tipo', 'Servicios', 'Cuota mensual', 'Consumo', 'Comisión', 'Impuesto',
            'Total facturado', 'Total a pagar', 'Total USD',
        ];

        $data = $rows->map(fn ($f) => [
            $f->numero_factura,
            $f->numero_cliente,
            $f->nombre_cliente,
            $f->periodo_desde?->format('Y-m-d'),
            $f->periodo_hasta?->format('Y-m-d'),
            $f->tipo_factura,
            $f->servicios_count,
            $f->total_cuota_mensual,
            $f->total_consumo,
            $f->total_comision,
            $f->total_impuesto,
            $f->total_facturado,
            $f->total_a_pagar,
            $f->total_usd,
        ])->all();

        return TabularExport::download(
            (string) $request->get('format', 'csv'),
            'Facturas ETECSA',
            $headers,
            $data,
            'facturas-etecsa'
        );
    }

    public function exportByEntity(EtecsaFactura $factura): HttpResponse
    {
        $factura->load([
            'servicios.connectivityRecord.salesFloor.entity:id,name,code',
            'servicios.salesFloorDirect.entity:id,name,code',
            'servicios.department.entity:id,name,code',
        ]);

        if (! class_exists(Spreadsheet::class)) {
            $headers = ['Entidad', 'Codigo entidad', 'Servicios', 'Cuota', 'Consumo', 'Comision', 'Impuesto', 'Total'];
            $rows = $this->buildEntitySummaryRows($factura);
            $data = array_map(fn ($r) => [
                $r['entity_name'],
                $r['entity_code'],
                $r['services_count'],
                $r['cuota_facturada'],
                $r['consumo'],
                $r['comision'],
                $r['impuesto'],
                $r['total_servicio'],
            ], $rows);

            return TabularExport::download('csv', 'Gasto ETECSA por entidad', $headers, $data, 'etecsa-entidad');
        }

        $rows = $this->buildEntitySummaryRows($factura);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Gasto por entidad');

        $headers = ['Entidad', 'Codigo entidad', 'Servicios', 'Cuota (CUP)', 'Consumo (CUP)', 'Comision (CUP)', 'Impuesto (CUP)', 'Total (CUP)'];
        $sheet->setCellValue('A1', 'Factura ETECSA: '.$factura->numero_factura);
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A2', 'Periodo: '.$factura->periodo_desde?->format('d/m/Y').' - '.$factura->periodo_hasta?->format('d/m/Y'));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A3', 'Resumen de gasto por entidad (para Economia)');
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $headerRow = 5;
        $sheet->fromArray([$headers], null, 'A'.$headerRow);
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        $dataRows = array_map(fn ($r) => [
            $r['entity_name'],
            $r['entity_code'],
            $r['services_count'],
            $r['cuota_facturada'],
            $r['consumo'],
            $r['comision'],
            $r['impuesto'],
            $r['total_servicio'],
        ], $rows);
        if ($dataRows !== []) {
            $sheet->fromArray($dataRows, null, 'A'.($headerRow + 1));
        }

        $totalRow = $headerRow + max(count($dataRows), 0) + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL GENERAL');
        $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
        $sheet->setCellValue("C{$totalRow}", array_sum(array_column($rows, 'services_count')));
        $sheet->setCellValue("D{$totalRow}", array_sum(array_column($rows, 'cuota_facturada')));
        $sheet->setCellValue("E{$totalRow}", array_sum(array_column($rows, 'consumo')));
        $sheet->setCellValue("F{$totalRow}", array_sum(array_column($rows, 'comision')));
        $sheet->setCellValue("G{$totalRow}", array_sum(array_column($rows, 'impuesto')));
        $sheet->setCellValue("H{$totalRow}", array_sum(array_column($rows, 'total_servicio')));
        $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFont()->setBold(true);

        $lastRow = $totalRow;
        $sheet->getStyle("A{$headerRow}:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D".($headerRow + 1).":H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("C".($headerRow + 1).":C{$lastRow}")->getNumberFormat()->setFormatCode('0');
        for ($c = 1; $c <= 8; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'sgi_etecsa_ent_');
        if ($tempPath === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal.');
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        $file = 'etecsa_'.$factura->numero_factura.'_gasto_por_entidad_'.now()->format('Ymd_His').'.xlsx';
        return response()->download($tempPath, $file, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function buildEntitySummaryRows(EtecsaFactura $factura): array
    {
        $byEntity = [];
        foreach ($factura->servicios as $svc) {
            $entity = $svc->connectivityRecord?->salesFloor?->entity
                ?? $svc->salesFloorDirect?->entity
                ?? $svc->department?->entity;

            $key = $entity?->id ? 'e_'.$entity->id : 'sin_entidad';
            if (! isset($byEntity[$key])) {
                $byEntity[$key] = [
                    'entity_name' => $entity?->name ?? 'Sin entidad vinculada',
                    'entity_code' => $entity?->code ?? '',
                    'services_count' => 0,
                    'cuota_facturada' => 0.0,
                    'consumo' => 0.0,
                    'comision' => 0.0,
                    'impuesto' => 0.0,
                    'total_servicio' => 0.0,
                ];
            }

            $byEntity[$key]['services_count']++;
            $byEntity[$key]['cuota_facturada'] += (float) ($svc->cuota_facturada ?? 0);
            $byEntity[$key]['consumo'] += (float) ($svc->consumo ?? 0);
            $byEntity[$key]['comision'] += (float) ($svc->comision ?? 0);
            $byEntity[$key]['impuesto'] += (float) ($svc->impuesto ?? 0);
            $byEntity[$key]['total_servicio'] += (float) ($svc->total_servicio ?? 0);
        }

        $rows = array_values($byEntity);
        usort($rows, fn ($a, $b) => strcmp(($a['entity_code'] ?: $a['entity_name']), ($b['entity_code'] ?: $b['entity_name'])));

        return $rows;
    }
}
