<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExcelImportHelpers;
use App\Models\AdslMode;
use App\Models\ConnectivityRecord;
use App\Models\ContractedSpeed;
use App\Models\Entity;
use App\Models\SalesFloor;
use App\Models\SystemSetting;
use App\Support\TabularExport;
use App\Support\UserEntityAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ConnectivityRecordController extends Controller
{
    use ExcelImportHelpers;

    public function index(Request $request): InertiaResponse
    {
        $records = ConnectivityRecord::query()
            ->whereHas('salesFloor', function ($q) use ($request) {
                UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id');
            })
            ->with([
                'salesFloor:id,name,address,phone,entity_id,municipio_code,datacell_piso_id',
                'salesFloor.entity:id,name,code',
                'etecsaServicios' => function ($q) {
                    $q->select([
                        'id',
                        'factura_id',
                        'connectivity_record_id',
                        'numero_servicio',
                        'cuota_facturada',
                        'total_servicio',
                    ])
                        ->orderByDesc('id')
                        ->with(['factura:id,numero_factura,periodo_desde,periodo_hasta,tipo_factura']);
                },
            ])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->whereHas('salesFloor', function ($sq) use ($search) {
                        $sq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('address', 'ilike', "%{$search}%")
                            ->orWhere('phone', 'ilike', "%{$search}%");
                    })
                        ->orWhere('ip_wan', 'ilike', "%{$search}%")
                        ->orWhere('ip_lan', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('sales_floor_id'), fn ($q) => $q->where('sales_floor_id', (int) $request->sales_floor_id))
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Connectivity/Index', [
            'records'          => $records,
            'linkModes'        => AdslMode::activo()->orderBy('code')->get(['id', 'code', 'nombre']),
            'contractedSpeeds' => ContractedSpeed::activo()->orderByRaw('kbps IS NULL, kbps ASC, nombre ASC')->get(['id', 'nombre', 'kbps']),
            'filters'   => [
                'search'               => $request->search,
                'sales_floor_id'       => $request->filled('sales_floor_id') ? (int) $request->sales_floor_id : null,
                'sales_floor_snapshot' => $request->filled('sales_floor_id')
                    ? $this->salesFloorFilterSnapshot($request->sales_floor_id)
                    : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $this->assertSalesFloorEntityAccess($request, (int) $data['sales_floor_id']);
        ConnectivityRecord::create($this->prepareData($data));

        return back()->with('success', 'Registro de conectividad creado.');
    }

    public function update(Request $request, ConnectivityRecord $conectividade): RedirectResponse
    {
        $data = $this->validateData($request);
        $this->assertSalesFloorEntityAccess($request, (int) $data['sales_floor_id']);
        $conectividade->update($this->prepareData($data));

        return back()->with('success', 'Registro de conectividad actualizado.');
    }

    public function destroy(ConnectivityRecord $conectividade): RedirectResponse
    {
        $conectividade->delete();

        return back()->with('success', 'Registro de conectividad eliminado.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Importación Excel ETECSA
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Vista previa: parsea el Excel ETECSA, intenta match automático con pisos
     * y devuelve registros con estado matched/unmatched/skipped.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xls,xlsx',
        ]);

        try {
            $parsed = $this->parseEtecsa($request->file('excel_file')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // Indexar pisos existentes por entity_id para match rápido
        $floorsByEntity = SalesFloor::query()
            ->whereNotNull('entity_id')
            ->get(['id', 'name', 'entity_id'])
            ->groupBy('entity_id');

        $records = [];
        $summary = ['matched' => 0, 'unmatched' => 0, 'skipped' => 0];

        foreach ($parsed['records'] as $rec) {
            if ($rec['entity_id'] === null) {
                $rec['match_status'] = 'skipped';
                $rec['sales_floor_id'] = null;
                $rec['available_floors'] = [];
                $summary['skipped']++;
                $records[] = $rec;
                continue;
            }

            $entityFloors = $floorsByEntity->get($rec['entity_id'], collect());
            $normalizedExcelName = self::normalizeName($rec['floor_name']);

            // Intentar match exacto por nombre normalizado
            $matched = $entityFloors->first(
                fn ($f) => self::normalizeName($f->name) === $normalizedExcelName
            );

            if ($matched) {
                $rec['match_status'] = 'matched';
                $rec['sales_floor_id'] = $matched->id;
                $rec['matched_floor_name'] = $matched->name;
                $rec['available_floors'] = [];
                $summary['matched']++;
            } else {
                $rec['match_status'] = 'unmatched';
                $rec['sales_floor_id'] = null;
                // Enviar pisos disponibles de esa entidad para el modal
                $rec['available_floors'] = $entityFloors->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                ])->values()->all();
                $summary['unmatched']++;
            }

            $records[] = $rec;
        }

        return response()->json([
            'success' => true,
            'records' => $records,
            'summary' => $summary,
        ]);
    }

    /**
     * Autocompletado de entidades para vincular filas omitidas en la vista previa ETECSA.
     */
    public function searchEntitiesForImport(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['entities' => []]);
        }

        $entities = Entity::active()
            ->with('municipio:id,code,name')
            ->tap(fn ($query) => UserEntityAccess::applyToEntitiesQuery($query, $request->user()))
            ->where(function ($w) use ($q) {
                $w->where('name', 'ilike', "%{$q}%")
                    ->orWhere('code', 'ilike', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'code', 'name', 'municipio_code', 'municipio_id']);

        return response()->json([
            'entities' => $entities->map(function (Entity $e) {
                $mun = $e->municipio?->name;
                $label = ($mun ? $mun.' · ' : '').'['.$e->code.'] '.$e->name;

                return [
                    'id'             => $e->id,
                    'code'           => $e->code,
                    'name'           => $e->name,
                    'label'          => $label,
                    'municipio_code' => $e->municipio_code ?: $e->municipio?->code,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Vincula una fila omitida (código entidad Excel desconocido) a una entidad del sistema
     * y recalcula matched / unmatched con los pisos de esa entidad.
     */
    public function rebindEntityForImportPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_id' => 'required|exists:entidades,id',
            'record'    => 'required|array',
        ]);

        $rawRow = $data['record'];
        $pisoNombre = $rawRow['floor_name'] ?? $rawRow['unit_name'] ?? null;
        if ($pisoNombre === null || trim((string) $pisoNombre) === '') {
            return response()->json([
                'success' => false,
                'message' => 'La fila debe incluir el nombre del piso: floor_name (ETECSA) o unit_name (FINCIMEX).',
            ], 422);
        }

        $entity = Entity::active()
            ->with('municipio:id,code')
            ->findOrFail($data['entity_id']);

        $allowed = UserEntityAccess::allowedEntityIds($request->user());
        if ($allowed !== null && ! in_array((int) $entity->id, array_map('intval', $allowed), true)) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para vincular esta entidad.'], 403);
        }

        $rec = $this->stripInternalImportRowKeys($data['record']);

        $rec['entity_id']      = $entity->id;
        $rec['entity_code']    = $entity->code;
        $rec['entity_name']    = $entity->name;
        $rec['municipio_code'] = $entity->municipio_code ?: $entity->municipio?->code;

        $normalizedExcelName = self::normalizeName(trim((string) $pisoNombre));

        $entityFloors = SalesFloor::query()
            ->where('entity_id', $entity->id)
            ->get(['id', 'name']);

        $matched = $entityFloors->first(
            fn ($f) => self::normalizeName($f->name) === $normalizedExcelName
        );

        if ($matched) {
            $rec['match_status']       = 'matched';
            $rec['sales_floor_id']     = $matched->id;
            $rec['matched_floor_name'] = $matched->name;
            $rec['available_floors']   = [];
        } else {
            $rec['match_status']       = 'unmatched';
            $rec['sales_floor_id']     = null;
            $rec['matched_floor_name'] = null;
            $rec['available_floors']   = $entityFloors->map(fn ($f) => [
                'id'   => $f->id,
                'name' => $f->name,
            ])->values()->all();
        }

        return response()->json([
            'success' => true,
            'record'  => $rec,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stripInternalImportRowKeys(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_')) {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * Aplica registros seleccionados. Cada registro debe traer:
     * - sales_floor_id (resuelto) O create_new=true (crear piso nuevo)
     */
    public function applySelected(Request $request): JsonResponse
    {
        $data = $request->validate([
            'records'   => 'required|array|min:1',
            'records.*' => 'array',
        ]);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $speedKnownNames = ContractedSpeed::pluck('nombre')
            ->mapWithKeys(fn ($n) => [strtolower(trim($n)) => $n])
            ->all();

        DB::transaction(function () use ($data, &$created, &$updated, &$skipped, &$speedKnownNames) {
            foreach ($data['records'] as $rec) {
                $action = $this->persistRecord($rec, $speedKnownNames);
                match ($action) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default   => $skipped++,
                };
            }
        });

        $message = "Aplicado: {$created} creados, {$updated} actualizados";
        if ($skipped > 0) {
            $message .= ", {$skipped} omitidos";
        }
        $message .= '.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $rows = ConnectivityRecord::query()
            ->whereHas('salesFloor', function ($q) use ($request) {
                UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id');
            })
            ->with(['salesFloor:id,name,address,phone,entity_id,municipio_code', 'salesFloor.entity:id,name,code'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->whereHas('salesFloor', function ($sq) use ($search) {
                        $sq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('address', 'ilike', "%{$search}%")
                            ->orWhere('phone', 'ilike', "%{$search}%");
                    })
                        ->orWhere('ip_wan', 'ilike', "%{$search}%")
                        ->orWhere('ip_lan', 'ilike', "%{$search}%");
                });
            })
            ->when($request->filled('sales_floor_id'), fn ($q) => $q->where('sales_floor_id', (int) $request->sales_floor_id))
            ->when($request->contracted_speed, fn ($q, $speed) => $q->where('contracted_speed', $speed))
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Piso de venta', 'Codigo entidad', 'Entidad', 'Direccion', 'Telefono',
            'Tipo de enlace', 'ED', 'INA', 'ID Facturación', 'Velocidad ETECSA',
            'Velocidad contratada', 'Cuota', 'IP WAN', 'Segmento WAN (CIDR)', 'IP LAN', 'Segmento LAN (CIDR)',
            'Notas',
        ];
        $data = $rows->map(fn ($row) => [
            $row->salesFloor?->name ?? $row->unit_name,
            $row->salesFloor?->entity?->code,
            $row->salesFloor?->entity?->name,
            $row->salesFloor?->address ?? $row->address,
            $row->salesFloor?->phone,
            $row->tipo_enlace,
            $row->ed,
            $row->ina,
            $row->id_facturacion,
            $row->velocidad_etecsa,
            $row->contracted_speed,
            $row->cuota,
            $row->ip_wan,
            $row->wan_cidr,
            $row->ip_lan,
            $row->lan_cidr,
            $row->notes,
        ])->all();

        $format = strtolower((string) $request->get('format', 'csv'));

        if ($format === 'xlsx') {
            return $this->downloadConnectivityXlsxWithSummary($rows, $headers, $data);
        }
        if ($format === 'pdf') {
            return $this->downloadConnectivityPdf($rows);
        }

        return TabularExport::download(
            $format,
            'Conectividad',
            $headers,
            $data,
            'conectividad'
        );
    }

    private function downloadConnectivityXlsxWithSummary($rows, array $headers, array $data): HttpResponse
    {
        if (! class_exists(Spreadsheet::class)) {
            return TabularExport::download('csv', 'Conectividad', $headers, $data, 'conectividad');
        }

        $spreadsheet = new Spreadsheet();
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Resumen');
        $layout = \App\Support\Branding::resolvedLayout();
        $org = (string) ($layout['organization_name'] ?? 'Centro Laboral');
        $sys = (string) ($layout['system_name'] ?? 'SGI - Sistema de Gestion Informatica');
        $docTitle = (string) ($layout['header_title'] ?? 'GESTION DOCUMENTAL INSTITUCIONAL');

        $tipoCounts = $rows->groupBy(fn ($r) => trim((string) ($r->tipo_enlace ?? '')) ?: 'Sin tipo de enlace')
            ->map->count()
            ->sortDesc();
        $speedCounts = $rows->groupBy(fn ($r) => trim((string) ($r->contracted_speed ?? $r->velocidad_etecsa ?? '')) ?: 'Sin velocidad')
            ->map->count()
            ->sortDesc();
        $quotaCounts = $rows->groupBy(fn ($r) => ($r->cuota === null || $r->cuota === '') ? 'Sin cuota' : (string) $r->cuota)
            ->map->count()
            ->sortDesc();
        $duplicatedBandwidthGroups = $speedCounts->filter(fn ($count) => $count > 1);
        $sinConexion = $rows->filter(function ($r) {
            $hasNetworkData = trim((string) ($r->ip_wan ?? '')) !== ''
                || trim((string) ($r->wan_cidr ?? '')) !== ''
                || trim((string) ($r->ip_lan ?? '')) !== ''
                || trim((string) ($r->lan_cidr ?? '')) !== '';
            $tipo = mb_strtolower(trim((string) ($r->tipo_enlace ?? '')));
            $declaredNoConnection = str_contains($tipo, 'sin') && str_contains($tipo, 'conex');

            return ! $hasNetworkData || $declaredNoConnection;
        })->count();

        $summaryRows = [
            [$org, ''],
            [$sys, ''],
            [$docTitle, ''],
            ['Conectividad - Resumen', ''],
            ['', ''],
            ['Resumen general', 'Cantidad'],
            ['Total de registros', (string) $rows->count()],
            ['Registros sin conexion', (string) $sinConexion],
            ['Grupos con mismo ancho de banda (repetidos)', (string) $duplicatedBandwidthGroups->count()],
            ['', ''],
            ['Cantidad por tipo de enlace', 'Registros'],
        ];
        foreach ($tipoCounts as $tipo => $count) {
            $summaryRows[] = [(string) $tipo, (string) $count];
        }
        $summaryRows[] = ['', ''];
        $summaryRows[] = ['Cantidad por ancho de banda', 'Registros'];
        foreach ($speedCounts as $speed => $count) {
            $summaryRows[] = [(string) $speed, (string) $count];
        }
        $summaryRows[] = ['', ''];
        $summaryRows[] = ['Cantidad por cuota', 'Registros'];
        foreach ($quotaCounts as $quota => $count) {
            $summaryRows[] = [(string) $quota, (string) $count];
        }

        $summarySheet->fromArray($summaryRows, null, 'A1');
        $summarySheet->mergeCells('A1:B1');
        $summarySheet->mergeCells('A2:B2');
        $summarySheet->mergeCells('A3:B3');
        $summarySheet->mergeCells('A4:B4');
        $summarySheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $summarySheet->getStyle('A2')->getFont()->setSize(11);
        $summarySheet->getStyle('A3')->getFont()->setBold(true)->setSize(10);
        $summarySheet->getStyle('A3')->getFont()->getColor()->setRGB('1D4ED8');
        $summarySheet->getStyle('A6:B6')->getFont()->setBold(true);
        $summarySheet->getStyle('A11:B11')->getFont()->setBold(true);
        $speedHeaderRow = 13 + $tipoCounts->count();
        $summarySheet->getStyle("A{$speedHeaderRow}:B{$speedHeaderRow}")->getFont()->setBold(true);
        $quotaHeaderRow = $speedHeaderRow + 2 + $speedCounts->count();
        $summarySheet->getStyle("A{$quotaHeaderRow}:B{$quotaHeaderRow}")->getFont()->setBold(true);
        $summarySheet->getColumnDimension('A')->setAutoSize(true);
        $summarySheet->getColumnDimension('B')->setAutoSize(true);

        $detailsSheet = $spreadsheet->createSheet();
        $detailsSheet->setTitle('Conectividad');
        $lastCol = Coordinate::stringFromColumnIndex(max(count($headers), 1));
        $detailsSheet->setCellValue('A1', $org);
        $detailsSheet->mergeCells("A1:{$lastCol}1");
        $detailsSheet->setCellValue('A2', $sys);
        $detailsSheet->mergeCells("A2:{$lastCol}2");
        $detailsSheet->setCellValue('A3', $docTitle);
        $detailsSheet->mergeCells("A3:{$lastCol}3");
        $detailsSheet->setCellValue('A4', 'Conectividad');
        $detailsSheet->mergeCells("A4:{$lastCol}4");
        $detailsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $detailsSheet->getStyle('A2')->getFont()->setSize(11);
        $detailsSheet->getStyle('A3')->getFont()->setBold(true)->setSize(10);
        $detailsSheet->getStyle('A3')->getFont()->getColor()->setRGB('1D4ED8');
        $detailsSheet->getStyle('A4')->getFont()->setBold(true)->setSize(11);
        $detailsSheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerRow = 6;
        $detailsSheet->fromArray([$headers], null, 'A'.$headerRow);
        if ($data !== []) {
            $detailsSheet->fromArray($data, null, 'A'.($headerRow + 1));
        }
        $lastRow = max(count($data) + $headerRow, $headerRow);
        $detailsSheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $detailsSheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $detailsSheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $detailsSheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        for ($c = 1; $c <= max(count($headers), 1); $c++) {
            $detailsSheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $downloadName = 'conectividad_'.now()->format('Ymd_His').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'sgi_conn_xlsx_');
        if ($tempPath === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal de exportacion.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($tempPath, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function downloadConnectivityPdf($rows): HttpResponse
    {
        $tipoCounts = $rows->groupBy(fn ($r) => trim((string) ($r->tipo_enlace ?? '')) ?: 'Sin tipo')
            ->map->count()
            ->sortDesc()
            ->all();
        $speedCounts = $rows->groupBy(fn ($r) => trim((string) ($r->contracted_speed ?? $r->velocidad_etecsa ?? '')) ?: 'Sin velocidad')
            ->map->count()
            ->sortDesc()
            ->all();
        $quotaCounts = $rows->groupBy(fn ($r) => ($r->cuota === null || $r->cuota === '') ? 'Sin cuota' : (string) $r->cuota)
            ->map->count()
            ->sortDesc()
            ->all();
        $sinConexion = $rows->filter(function ($r) {
            $hasNetworkData = trim((string) ($r->ip_wan ?? '')) !== ''
                || trim((string) ($r->wan_cidr ?? '')) !== ''
                || trim((string) ($r->ip_lan ?? '')) !== ''
                || trim((string) ($r->lan_cidr ?? '')) !== '';
            $tipo = mb_strtolower(trim((string) ($r->tipo_enlace ?? '')));
            $declaredNoConnection = str_contains($tipo, 'sin') && str_contains($tipo, 'conex');

            return ! $hasNetworkData || $declaredNoConnection;
        })->count();

        $branding = SystemSetting::branding();
        $brandingLogoDataUrl = SystemSetting::logoDataUrl();

        $pdf = Pdf::loadView('pdf.connectivity_export', [
            'rows' => $rows,
            'tipoCounts' => $tipoCounts,
            'speedCounts' => $speedCounts,
            'quotaCounts' => $quotaCounts,
            'sinConexion' => $sinConexion,
            'total' => $rows->count(),
            'branding' => $branding,
            'brandingLogoDataUrl' => $brandingLogoDataUrl,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('conectividad_'.now()->format('Ymd_His').'.pdf');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Parsea el Excel ETECSA (14 columnas, sheet "ART").
     */
    private function parseEtecsa(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('ART') ?? $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, false, false);

        // Pre-cargar entidades
        $entityMap = \App\Models\Entity::active()
            ->with('municipio:id,code')
            ->get(['id', 'code', 'name', 'municipio_code', 'municipio_id'])
            ->keyBy('code');

        // Velocidades conocidas
        $speedKnownNames = ContractedSpeed::pluck('nombre')
            ->mapWithKeys(fn ($n) => [strtolower(trim($n)) => $n])
            ->all();

        $records = [];

        foreach ($rows as $row) {
            $rawCode    = trim((string) ($row[1] ?? ''));
            $entityCode = $rawCode !== '' ? str_pad($rawCode, 5, '0', STR_PAD_LEFT) : '';
            $floorName  = trim((string) ($row[2] ?? ''));

            // Saltar cabecera y filas vacías
            if ($floorName === '' || mb_strtolower($floorName) === 'entidad') {
                continue;
            }

            $entity        = $entityCode !== '' ? $entityMap->get($entityCode) : null;
            $entityId      = $entity?->id;
            $municipioCode = $entity?->municipio_code ?: $entity?->municipio?->code;

            $municipioExcel = trim((string) ($row[3] ?? ''));
            $address        = trim((string) ($row[4] ?? '')) ?: null;
            $tipoEnlace     = trim((string) ($row[5] ?? '')) ?: null;
            $ed             = trim((string) ($row[6] ?? '')) ?: null;
            $ina            = trim((string) ($row[7] ?? '')) ?: null;
            $idFacturacion  = trim((string) ($row[8] ?? '')) ?: null;
            $velocidadRaw   = trim((string) ($row[9] ?? ''));
            $cuotaRaw       = $row[10] ?? null;
            $ipWanRaw       = trim((string) ($row[11] ?? ''));
            $ipLanRaw       = trim((string) ($row[12] ?? ''));
            $wanParsed      = $this->extractIpAndCidr($ipWanRaw !== '' ? $ipWanRaw : null);
            $lanParsed      = $this->extractIpAndCidr($ipLanRaw !== '' ? $ipLanRaw : null);
            $observaciones  = trim((string) ($row[13] ?? '')) ?: null;

            // Cuota: puede venir como número o string con formato
            $cuota = null;
            if ($cuotaRaw !== null && $cuotaRaw !== '') {
                $cuotaStr = str_replace(',', '.', str_replace(' ', '', (string) $cuotaRaw));
                $cuota = is_numeric($cuotaStr) ? (float) $cuotaStr : null;
            }

            // Velocidad → nomenclador (preview: solo lectura, sin insertar filas)
            $contractedSpeed = $this->resolveSpeed($velocidadRaw, $speedKnownNames, false);

            $records[] = [
                // Display
                'entity_code'      => $entityCode,
                'entity_name'      => $entity?->name,
                'floor_name'       => $floorName,
                'municipio_excel'  => $municipioExcel,
                // Persistencia — SalesFloor
                'entity_id'        => $entityId,
                'municipio_code'   => $municipioCode,
                'address'          => $address,
                // Persistencia — ConnectivityRecord
                'tipo_enlace'      => $tipoEnlace,
                'ed'               => $ed,
                'ina'              => $ina,
                'id_facturacion'   => $idFacturacion,
                'velocidad_etecsa' => $velocidadRaw ?: null,
                'contracted_speed' => $contractedSpeed,
                'cuota'            => $cuota,
                'ip_wan'           => $wanParsed['ip'],
                'wan_cidr'         => $wanParsed['cidr'],
                'ip_lan'           => $lanParsed['ip'],
                'lan_cidr'         => $lanParsed['cidr'],
                'notes'            => $observaciones,
            ];
        }

        return ['records' => $records];
    }

    /**
     * Persiste un registro (SalesFloor + ConnectivityRecord).
     *
     * @param  array<string, string>  $speedKnownNames
     */
    private function persistRecord(array $rec, array &$speedKnownNames): string
    {
        // Si create_new, crear el piso con los datos del Excel
        if (!empty($rec['create_new'])) {
            $floor = SalesFloor::create([
                'name'           => $rec['floor_name'],
                'entity_id'      => $rec['entity_id'] ?? null,
                'municipio_code' => $rec['municipio_code'] ?? null,
                'address'        => $rec['address'],
            ]);
            $wasCreated = true;
        } elseif (!empty($rec['sales_floor_id'])) {
            $floor = SalesFloor::find($rec['sales_floor_id']);
            if (!$floor) {
                return 'skipped';
            }
            $expectedEntityId = $rec['entity_id'] ?? null;
            if ($expectedEntityId !== null && $floor->entity_id !== null
                && (int) $floor->entity_id !== (int) $expectedEntityId) {
                return 'skipped';
            }
            // Actualizar dirección si viene del Excel y el piso no tiene
            if (($rec['address'] ?? null) && !$floor->address) {
                $floor->update(['address' => $rec['address']]);
            }
            $wasCreated = false;
        } else {
            return 'skipped';
        }

        $contracted = isset($rec['contracted_speed']) && $rec['contracted_speed'] !== ''
            ? (string) $rec['contracted_speed']
            : null;
        $velRaw = trim((string) ($rec['velocidad_etecsa'] ?? ''));
        if ($contracted === null && $velRaw !== '') {
            $contracted = $this->resolveSpeed($velRaw, $speedKnownNames, true);
        }

        ConnectivityRecord::updateOrCreate(
            ['sales_floor_id' => $floor->id],
            [
                'unit_name'        => $rec['floor_name'],
                'address'          => $floor->address,
                'tipo_enlace'      => $rec['tipo_enlace'] ?? null,
                'ed'               => $rec['ed'] ?? null,
                'ina'              => $rec['ina'] ?? null,
                'id_facturacion'   => $rec['id_facturacion'] ?? null,
                'velocidad_etecsa' => $velRaw !== '' ? $velRaw : ($rec['velocidad_etecsa'] ?? null),
                'contracted_speed' => $contracted,
                'cuota'            => $rec['cuota'] ?? null,
                'ip_wan'           => $rec['ip_wan'] ?? null,
                'wan_cidr'         => $rec['wan_cidr'] ?? null,
                'ip_lan'           => $rec['ip_lan'] ?? null,
                'lan_cidr'         => $rec['lan_cidr'] ?? null,
                'notes'            => $rec['notes'] ?? null,
                'source_sheet'     => 'ETECSA',
            ]
        );

        return $wasCreated ? 'created' : 'updated';
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function assertSalesFloorEntityAccess(Request $request, int $salesFloorId): void
    {
        $allowed = UserEntityAccess::allowedEntityIds($request->user());
        if ($allowed === null) {
            return;
        }
        $entityId = SalesFloor::query()->whereKey($salesFloorId)->value('entity_id');
        if (! $entityId || $allowed === [] || ! in_array((int) $entityId, array_map('intval', $allowed), true)) {
            abort(403, 'No tiene permiso para operar con esta entidad.');
        }
    }

    private function salesFloorFilterSnapshot(mixed $salesFloorId): ?array
    {
        if ($salesFloorId === null || $salesFloorId === '') {
            return null;
        }
        $sf = SalesFloor::with(['entity:id,name,code'])->find((int) $salesFloorId);
        if (!$sf) {
            return null;
        }
        $base   = $sf->hierarchyLabelEntityCode();
        $suffix = $sf->datacell_piso_id ? ' (ID Piso: ' . $sf->datacell_piso_id . ')' : '';

        return [
            'id'    => $sf->id,
            'label' => $base . $suffix,
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'sales_floor_id'      => 'required|exists:pisos_venta,id',
            'unit_name'           => 'nullable|string|max:255',
            'address'             => 'nullable|string|max:255',
            'contracted_speed'    => 'nullable|string|max:50|exists:velocidades_contratadas,nombre',
            'tipo_enlace'         => 'nullable|string|max:50|exists:modos_adsl,code',
            'ed'                  => 'nullable|string|max:100',
            'ina'                 => 'nullable|string|max:100',
            'id_facturacion'      => 'nullable|string|max:100',
            'velocidad_etecsa'    => 'nullable|string|max:100',
            'cuota'               => 'nullable|numeric|min:0',
            'ip_wan'              => 'nullable|string|max:45',
            'wan_cidr'            => 'nullable|string|max:64',
            'ip_lan'              => 'nullable|string|max:45',
            'lan_cidr'            => 'nullable|string|max:64',
            'notes'               => 'nullable|string|max:2000',
        ]);
    }

    private function prepareData(array $data): array
    {
        $floor = SalesFloor::query()->find($data['sales_floor_id']);
        if ($floor) {
            $data['unit_name'] = $floor->name;
            $data['address']   = $floor->address;
        }

        if (array_key_exists('cuota', $data)) {
            $c = $data['cuota'];
            $data['cuota'] = ($c === null || $c === '' || $c === false) ? null : $c;
        }

        foreach (['wan_cidr', 'lan_cidr'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) ($data[$k] ?? ''));
                $data[$k] = $v === '' ? null : $v;
            }
        }

        // Una sola entrada CIDR en formulario: derivar IP ancla para búsquedas, export y ping (compat. Excel).
        foreach (['wan_cidr' => 'ip_wan', 'lan_cidr' => 'ip_lan'] as $cidrKey => $ipKey) {
            if (! array_key_exists($cidrKey, $data)) {
                continue;
            }
            $cidr = $data[$cidrKey];
            if ($cidr === null || $cidr === '') {
                $data[$ipKey] = null;

                continue;
            }
            $parsed = $this->extractIpAndCidr($cidr);
            $data[$ipKey] = $parsed['ip'];
        }

        return $data;
    }

    public function comprobaciones(Request $request): InertiaResponse
    {
        return Inertia::render('Connectivity/Comprobaciones');
    }

    /**
     * Datos de conectividad para un piso de venta (comprobaciones / ping).
     */
    public function comprobacionesRegistro(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales_floor_id' => 'required|exists:pisos_venta,id',
        ]);

        $floor = SalesFloor::query()
            ->with(['entity:id,name,code'])
            ->findOrFail((int) $data['sales_floor_id']);

        $allowed = UserEntityAccess::allowedEntityIds($request->user());
        if ($allowed !== null) {
            $eid = $floor->entity_id;
            if (! $eid || $allowed === [] || ! in_array((int) $eid, array_map('intval', $allowed), true)) {
                return response()->json(['success' => false, 'message' => 'No tiene acceso a este piso de venta.'], 403);
            }
        }

        $rec = ConnectivityRecord::query()
            ->where('sales_floor_id', $floor->id)
            ->first();

        return response()->json([
            'success'     => true,
            'sales_floor' => [
                'id'        => $floor->id,
                'name'      => $floor->name,
                'entity'    => $floor->entity,
            ],
            'record' => $rec,
        ]);
    }
}
