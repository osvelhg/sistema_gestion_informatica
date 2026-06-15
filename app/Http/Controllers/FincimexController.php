<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExcelImportHelpers;
use App\Http\Controllers\Concerns\ValidatesAreaVentaFields;
use App\Models\AdslMode;
use App\Models\AreaVenta;
use App\Models\EstablishmentStatus;
use App\Models\EstablishmentType;
use App\Models\NetworkType;
use App\Models\SalesFloor;
use App\Support\TabularExport;
use App\Support\UserEntityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FincimexController extends Controller
{
    use ValidatesAreaVentaFields;
    use ExcelImportHelpers;

    public function index(Request $request): Response
    {
        // Una fila por piso de venta (resumen de todas las áreas FINCIMEX de ese PV)
        $records = SalesFloor::query()
            ->tap(fn ($q) => UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id'))
            ->whereHas('areasVenta')
            ->with([
                'areasVenta' => fn ($q) => $q->orderBy('name'),
                'entity:id,name,code',
                'municipio:id,name',
                'establishmentStatus:id,name',
            ])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('name', 'ilike', "%{$search}%")
                        ->orWhere('address', 'ilike', "%{$search}%")
                        ->orWhereHas('entity', fn ($eq) => $eq->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('areasVenta', fn ($aq) => $aq->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->filled('sales_floor_id'), fn ($q) => $q->where('id', (int) $request->sales_floor_id))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $records->setCollection(
            $records->getCollection()->map(function (SalesFloor $floor) {
                $floor->areasVenta->each(function (AreaVenta $a) use ($floor) {
                    $a->setRelation('salesFloor', $floor);
                });

                return $floor;
            })
        );

        return Inertia::render('Fincimex/Index', [
            'records' => $records,
            'filters' => [
                'search'               => $request->search,
                'sales_floor_id'       => $request->filled('sales_floor_id') ? (int) $request->sales_floor_id : null,
                'sales_floor_snapshot' => $request->filled('sales_floor_id')
                    ? $this->salesFloorSnapshot($request->sales_floor_id)
                    : null,
            ],
            'cashRegisterModels' => AreaVenta::CASH_REGISTER_MODELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AreaVenta::create($this->validateAreaVentaFields($request));

        return back()->with('success', 'Área de venta creada.');
    }

    public function update(Request $request, AreaVenta $fincimex): RedirectResponse
    {
        $fincimex->update($this->validateAreaVentaFields($request));

        return back()->with('success', 'Área de venta actualizada.');
    }

    public function destroy(AreaVenta $fincimex): RedirectResponse
    {
        $fincimex->delete();

        return back()->with('success', 'Área de venta eliminada.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Importación Excel FINCIMEX
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Vista previa: parsea el Excel FINCIMEX, intenta match automático con pisos
     * y devuelve registros con estado matched/unmatched/skipped.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xls,xlsx',
        ]);

        try {
            $parsed = $this->parseFincimex($request->file('excel_file')->getRealPath());
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
            // Igual que ETECSA: sin entidad resuelta → omitido (código vacío o no existe en sistema)
            if ($rec['entity_id'] === null) {
                $rec['match_status'] = 'skipped';
                $rec['sales_floor_id'] = null;
                $rec['available_floors'] = [];
                $summary['skipped']++;
                $records[] = $rec;
                continue;
            }

            $entityFloors = $floorsByEntity->get($rec['entity_id'], collect());
            $normalizedName = self::normalizeName($rec['unit_name']);

            // Match solo por entidad + nombre normalizado (sin fallback global: evita cruzar entidades)
            $matched = $entityFloors->first(
                fn ($f) => self::normalizeName($f->name) === $normalizedName
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
     * Aplica registros seleccionados.
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

        DB::transaction(function () use ($data, &$created, &$updated, &$skipped) {
            foreach ($data['records'] as $rec) {
                $action = $this->persistImportRecord($rec);
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
        $rows = AreaVenta::query()
            ->whereHas('salesFloor', function ($q) use ($request) {
                UserEntityAccess::whereEntityIdAllowed($q, $request->user(), 'entity_id');
            })
            ->with([
                'salesFloor:id,name,address,entity_id,municipio_code',
                'salesFloor.entity:id,name,code',
                'salesFloor.municipio:id,name',
            ])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('name', 'ilike', "%{$search}%")
                        ->orWhereHas('salesFloor', fn ($sq) => $sq->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->filled('sales_floor_id'), fn ($q) => $q->where('sales_floor_id', (int) $request->sales_floor_id))
            ->orderByDesc('id')
            ->get();

        $modelNames = AreaVenta::CASH_REGISTER_MODELS;

        $headers = [
            'Piso de venta', 'Área de venta', 'Municipio', 'Entidad',
            'TPV (Cajas)', 'POS Telef.', 'POS IP', 'Dem. POS IP',
            'POS GPRS', 'Dem. POS GPRS', 'Conectividad IP', 'POS Rotos',
            'Modelo caja', 'Moneda MLC', 'Moneda CUP',
            'QR Fincimex MLC', 'QR Fincimex CUP',
            'Source QR MLC', 'Source QR CUP', 'Terminal ID',
        ];

        $data = $rows->map(fn ($r) => [
            $r->salesFloor?->name,
            $r->name,
            $r->salesFloor?->municipio?->name,
            $r->salesFloor?->entity?->name,
            $r->tpv_boxes,
            $r->pos_phone_qty,
            $r->pos_ip_qty,
            $r->pos_ip_demand,
            $r->pos_gprs_qty,
            $r->pos_gprs_demand,
            $r->has_ip_connectivity ? 'Sí' : 'No',
            $r->broken_pos_qty,
            $r->cash_register_model_code ? ($modelNames[$r->cash_register_model_code] ?? $r->cash_register_model_code) : null,
            $r->pos_currency_mlc ? 'Sí' : 'No',
            $r->pos_currency_cup ? 'Sí' : 'No',
            $r->qr_fincimex_mlc ? 'Sí' : 'No',
            $r->qr_fincimex_cup ? 'Sí' : 'No',
            $r->src_fincimex_mlc,
            $r->src_fincimex_cup,
            $r->terminal_id,
        ])->all();

        $format = strtolower((string) $request->get('format', 'csv'));
        if ($format === 'xlsx') {
            return $this->downloadFincimexXlsxWithSummaries($rows, $headers, $data, $modelNames);
        }

        return TabularExport::download(
            $format,
            'FINCIMEX',
            $headers,
            $data,
            'fincimex'
        );
    }

    private function downloadFincimexXlsxWithSummaries($rows, array $headers, array $data, array $modelNames): HttpResponse
    {
        if (! class_exists(Spreadsheet::class)) {
            return TabularExport::download('csv', 'FINCIMEX', $headers, $data, 'fincimex');
        }

        $spreadsheet = new Spreadsheet();
        $layout = \App\Support\Branding::resolvedLayout();
        $org = (string) ($layout['organization_name'] ?? 'Centro Laboral');
        $sys = (string) ($layout['system_name'] ?? 'SGI - Sistema de Gestion Informatica');
        $docTitle = (string) ($layout['header_title'] ?? 'GESTION DOCUMENTAL INSTITUCIONAL');

        // Hoja 1: Resumen general
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumen general');
        $summaryRows = [
            [$org, ''],
            [$sys, ''],
            [$docTitle, ''],
            ['FINCIMEX - Resumen general', ''],
            ['', ''],
            ['Concepto', 'Cantidad'],
            ['Registros (areas de venta)', (string) $rows->count()],
            ['Pisos de venta con datos', (string) $rows->pluck('sales_floor_id')->filter()->unique()->count()],
            ['Entidades', (string) $rows->map(fn ($r) => $r->salesFloor?->entity?->id)->filter()->unique()->count()],
            ['TPV (cajas) total', (string) $rows->sum(fn ($r) => (int) ($r->tpv_boxes ?? 0))],
            ['POS telefono total', (string) $rows->sum(fn ($r) => (int) ($r->pos_phone_qty ?? 0))],
            ['POS IP total', (string) $rows->sum(fn ($r) => (int) ($r->pos_ip_qty ?? 0))],
            ['POS GPRS total', (string) $rows->sum(fn ($r) => (int) ($r->pos_gprs_qty ?? 0))],
            ['POS rotos total', (string) $rows->sum(fn ($r) => (int) ($r->broken_pos_qty ?? 0))],
            ['Conectividad IP = Si', (string) $rows->filter(fn ($r) => (bool) $r->has_ip_connectivity)->count()],
            ['Conectividad IP = No', (string) $rows->filter(fn ($r) => ! (bool) $r->has_ip_connectivity)->count()],
            ['Moneda MLC = Si', (string) $rows->filter(fn ($r) => (bool) $r->pos_currency_mlc)->count()],
            ['Moneda CUP = Si', (string) $rows->filter(fn ($r) => (bool) $r->pos_currency_cup)->count()],
            ['QR Fincimex MLC = Si', (string) $rows->filter(fn ($r) => (bool) $r->qr_fincimex_mlc)->count()],
            ['QR Fincimex CUP = Si', (string) $rows->filter(fn ($r) => (bool) $r->qr_fincimex_cup)->count()],
        ];
        $summary->fromArray($summaryRows, null, 'A1');
        $summary->mergeCells('A1:B1');
        $summary->mergeCells('A2:B2');
        $summary->mergeCells('A3:B3');
        $summary->mergeCells('A4:B4');
        $summary->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $summary->getStyle('A2')->getFont()->setSize(11);
        $summary->getStyle('A3')->getFont()->setBold(true)->setSize(10);
        $summary->getStyle('A3')->getFont()->getColor()->setRGB('1D4ED8');
        $summary->getStyle('A6:B6')->getFont()->setBold(true);
        $summary->getColumnDimension('A')->setAutoSize(true);
        $summary->getColumnDimension('B')->setAutoSize(true);

        // Hoja 2: Resumen por piso/unidad
        $byFloor = $rows->groupBy(fn ($r) => (string) ($r->sales_floor_id ?? 'sf_'.$r->id));
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Resumen por piso');
        $headers2 = [
            'Entidad',
            'Codigo entidad',
            'Unidad / Piso de venta',
            'Areas de venta',
            'TPV (cajas)',
            'POS Telef.',
            'POS IP',
            'POS GPRS',
            'POS Rotos',
            'Conectividad IP (si)',
            'MLC (si)',
            'CUP (si)',
        ];
        $rows2 = [];
        foreach ($byFloor as $group) {
            $first = $group->first();
            $rows2[] = [
                $first->salesFloor?->entity?->name ?? '',
                $first->salesFloor?->entity?->code ?? '',
                $first->salesFloor?->name ?? $first->name ?? '',
                (string) $group->count(),
                (string) $group->sum(fn ($r) => (int) ($r->tpv_boxes ?? 0)),
                (string) $group->sum(fn ($r) => (int) ($r->pos_phone_qty ?? 0)),
                (string) $group->sum(fn ($r) => (int) ($r->pos_ip_qty ?? 0)),
                (string) $group->sum(fn ($r) => (int) ($r->pos_gprs_qty ?? 0)),
                (string) $group->sum(fn ($r) => (int) ($r->broken_pos_qty ?? 0)),
                (string) $group->filter(fn ($r) => (bool) $r->has_ip_connectivity)->count(),
                (string) $group->filter(fn ($r) => (bool) $r->pos_currency_mlc)->count(),
                (string) $group->filter(fn ($r) => (bool) $r->pos_currency_cup)->count(),
            ];
        }
        $sheet2->fromArray([$headers2], null, 'A1');
        if ($rows2 !== []) {
            $sheet2->fromArray($rows2, null, 'A2');
        }
        $lastCol2 = Coordinate::stringFromColumnIndex(count($headers2));
        $lastRow2 = max(count($rows2) + 1, 1);
        $sheet2->getStyle("A1:{$lastCol2}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet2->getStyle("A1:{$lastCol2}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet2->getStyle("A1:{$lastCol2}{$lastRow2}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        for ($c = 1; $c <= count($headers2); $c++) {
            $sheet2->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        // Hojas por entidad
        $byEntity = $rows->groupBy(function ($r) {
            $entityId = $r->salesFloor?->entity?->id;

            return $entityId ? ('e_'.$entityId) : 'sin_entidad';
        });
        foreach ($byEntity as $group) {
            $first = $group->first();
            $entityName = (string) ($first->salesFloor?->entity?->name ?? 'Sin entidad');
            $entityCode = (string) ($first->salesFloor?->entity?->code ?? '');
            $rawTitle = trim(($entityCode !== '' ? '['.$entityCode.'] ' : '').$entityName);
            $sheetName = $this->uniqueSheetTitle($spreadsheet, $rawTitle);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            $sheet->setCellValue('A1', $org);
            $sheet->mergeCells('A1:T1');
            $sheet->setCellValue('A2', $sys);
            $sheet->mergeCells('A2:T2');
            $sheet->setCellValue('A3', 'Detalle por entidad: '.$entityName.($entityCode !== '' ? ' ['.$entityCode.']' : ''));
            $sheet->mergeCells('A3:T3');
            $sheet->setCellValue('A4', 'Pisos incluidos: '.$group->pluck('sales_floor_id')->filter()->unique()->count().' | Areas: '.$group->count());
            $sheet->mergeCells('A4:T4');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A3')->getFont()->setBold(true);

            $sheet->fromArray([$headers], null, 'A6');
            $sheetData = $group->map(fn ($r) => [
                $r->salesFloor?->name,
                $r->name,
                $r->salesFloor?->municipio?->name,
                $r->salesFloor?->entity?->name,
                (string) ($r->tpv_boxes ?? 0),
                (string) ($r->pos_phone_qty ?? 0),
                (string) ($r->pos_ip_qty ?? 0),
                (string) ($r->pos_ip_demand ?? 0),
                (string) ($r->pos_gprs_qty ?? 0),
                (string) ($r->pos_gprs_demand ?? 0),
                $r->has_ip_connectivity ? 'Sí' : 'No',
                (string) ($r->broken_pos_qty ?? 0),
                $r->cash_register_model_code ? ($modelNames[$r->cash_register_model_code] ?? $r->cash_register_model_code) : null,
                $r->pos_currency_mlc ? 'Sí' : 'No',
                $r->pos_currency_cup ? 'Sí' : 'No',
                $r->qr_fincimex_mlc ? 'Sí' : 'No',
                $r->qr_fincimex_cup ? 'Sí' : 'No',
                $r->src_fincimex_mlc,
                $r->src_fincimex_cup,
                $r->terminal_id,
            ])->all();
            if ($sheetData !== []) {
                $sheet->fromArray($sheetData, null, 'A7');
            }
            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $lastRow = max(count($sheetData) + 6, 6);
            $sheet->getStyle("A6:{$lastCol}6")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
            $sheet->getStyle("A6:{$lastCol}6")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A6:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            for ($c = 1; $c <= count($headers); $c++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }
        }

        $downloadName = 'fincimex_'.now()->format('Ymd_His').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'sgi_fincimex_xlsx_');
        if ($tempPath === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal de exportacion.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($tempPath, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function uniqueSheetTitle(Spreadsheet $spreadsheet, string $raw): string
    {
        $clean = preg_replace('/[\\\\\\/*?:\\[\\]]/u', ' ', $raw) ?? 'Unidad';
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));
        if ($clean === '') {
            $clean = 'Unidad';
        }

        $base = mb_substr($clean, 0, 31);
        $title = $base;
        $i = 2;
        while ($spreadsheet->getSheetByName($title) !== null) {
            $suffix = ' '.$i;
            $title = mb_substr($base, 0, max(1, 31 - mb_strlen($suffix))).$suffix;
            $i++;
        }

        return $title;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Parsea el Excel FINCIMEX (64 columnas, sheet "ART").
     */
    private function parseFincimex(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('ART') ?? $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, false, false);

        $networkTypeMap = NetworkType::pluck('id', 'name')->all();
        $estTypeMap     = EstablishmentType::pluck('id', 'name')->all();
        $estStatusMap   = EstablishmentStatus::pluck('id', 'name')->all();
        $adslKnownCodes = AdslMode::pluck('code')->map(fn ($c) => strtoupper($c))->flip()->all();

        $entityMap = \App\Models\Entity::active()
            ->with('municipio:id,code')
            ->get(['id', 'code', 'name', 'municipio_code', 'municipio_id'])
            ->keyBy('code');

        $networkColMap = [
            51 => 'RED CUP',
            52 => 'RED MLC',
            53 => 'Mixta',
            55 => 'Hotelera',
            57 => 'Virtual',
            58 => 'Mi Pieza',
        ];

        $records = [];

        foreach ($rows as $row) {
            $rawCode    = trim((string) ($row[1] ?? ''));
            $entityCode = $rawCode !== '' ? str_pad($rawCode, 5, '0', STR_PAD_LEFT) : '';
            $unitName   = trim((string) ($row[2] ?? ''));
            $areaName   = trim((string) ($row[3] ?? ''));

            if ($unitName === '' || mb_strtolower($unitName) === 'unidad, pv o kiosco') {
                continue;
            }

            // Si no hay nombre de área, usar 'Principal' como default
            if ($areaName === '' || mb_strtolower($areaName) === 'departamento') {
                $areaName = 'Principal';
            }

            $entity        = $entityCode !== '' ? $entityMap->get($entityCode) : null;
            $entityId      = $entity?->id;
            $municipioCode = $entity?->municipio_code ?: $entity?->municipio?->code;

            // Clasificación tipo red
            $networkTypeName = null;
            foreach ($networkColMap as $col => $name) {
                if ($this->toInt($row[$col] ?? 0) === 1) {
                    $networkTypeName = $name;
                    break;
                }
            }

            $hasMlc = $this->toInt($row[15] ?? 0) === 1;
            $hasCup = $this->toInt($row[16] ?? 0) === 1;
            $estTypeName = match (true) {
                $hasMlc && $hasCup => 'MIXTO',
                $hasMlc            => 'MLC',
                $hasCup            => 'CUP',
                default            => null,
            };

            $isOpen   = $this->toInt($row[46] ?? 0) === 1;
            $isClosed = $this->toInt($row[47] ?? 0) === 1;
            $estStatusName = match (true) {
                $isOpen   => 'Abierto',
                $isClosed => 'Cerrado',
                default   => null,
            };

            $records[] = [
                // Display
                'entity_code'               => $entityCode,
                'entity_name'               => $entity?->name,
                'unit_name'                 => $unitName,
                'area_name'                 => $areaName,
                'network_type_name'         => $networkTypeName,
                'establishment_type_name'   => $estTypeName,
                'establishment_status_name' => $estStatusName,
                // SalesFloor
                'entity_id'               => $entityId,
                'municipio_code'          => $municipioCode,
                'address'                 => trim((string) ($row[4] ?? '')) ?: null,
                'network_type_id'         => $networkTypeName ? ($networkTypeMap[$networkTypeName] ?? null) : null,
                'establishment_type_id'   => $estTypeName ? ($estTypeMap[$estTypeName] ?? null) : null,
                'establishment_status_id' => $estStatusName ? ($estStatusMap[$estStatusName] ?? null) : null,
                'latitude'                => $this->toCoordString($row[61] ?? null),
                'longitude'               => $this->toCoordString($row[62] ?? null),
                // AreaVenta
                'tpv_boxes'                 => $this->toInt($row[5] ?? 0),
                'pos_phone_qty'             => $this->toInt($row[6] ?? 0),
                'pos_ip_qty'                => $this->toInt($row[7] ?? 0),
                'pos_ip_demand'             => $this->toInt($row[8] ?? 0),
                'pos_gprs_qty'              => $this->toInt($row[9] ?? 0),
                'pos_gprs_demand'           => $this->toInt($row[10] ?? 0),
                'has_ip_connectivity'       => $this->toInt($row[11] ?? 0) === 1,
                'broken_pos_qty'            => $this->toInt($row[12] ?? 0),
                'cash_register_model_code'  => ($v = $this->toInt($row[14] ?? 0)) > 0 ? $v : null,
                'pos_currency_mlc'          => $hasMlc,
                'pos_currency_cup'          => $hasCup,
                'qr_fincimex_mlc'           => $this->toInt($row[21] ?? 0) === 1,
                'qr_fincimex_cup'           => $this->toInt($row[22] ?? 0) === 1,
                'src_fincimex_mlc'          => trim((string) ($row[29] ?? '')) ?: null,
                'src_fincimex_cup'          => trim((string) ($row[30] ?? '')) ?: null,
                'terminal_id'               => trim((string) ($row[41] ?? '')) ?: null,
            ];
        }

        return ['records' => $records];
    }

    /**
     * Persiste un registro importado (SalesFloor + AreaVenta).
     */
    private function persistImportRecord(array $rec): string
    {
        // Resolver piso de venta
        if (!empty($rec['create_new'])) {
            $floor = SalesFloor::create([
                'name'                    => $rec['unit_name'],
                'entity_id'               => $rec['entity_id'] ?? null,
                'municipio_code'          => $rec['municipio_code'] ?? null,
                'address'                 => $rec['address'],
                'network_type_id'         => $rec['network_type_id'] ?? null,
                'establishment_type_id'   => $rec['establishment_type_id'] ?? null,
                'establishment_status_id' => $rec['establishment_status_id'] ?? null,
                'latitude'                => $rec['latitude'] ?? null,
                'longitude'               => $rec['longitude'] ?? null,
            ]);
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
            // Actualizar datos del piso con la información del Excel
            $floor->update(array_filter([
                'network_type_id'         => $rec['network_type_id'] ?? null,
                'establishment_type_id'   => $rec['establishment_type_id'] ?? null,
                'establishment_status_id' => $rec['establishment_status_id'] ?? null,
                'latitude'                => $rec['latitude'] ?? null,
                'longitude'               => $rec['longitude'] ?? null,
            ], fn ($v) => $v !== null));
        } else {
            return 'skipped';
        }

        $wasNew = $floor->wasRecentlyCreated;

        // Crear/actualizar AreaVenta
        AreaVenta::updateOrCreate(
            [
                'sales_floor_id' => $floor->id,
                'name'           => $rec['area_name'],
            ],
            [
                'tpv_boxes'                => $rec['tpv_boxes'] ?? 0,
                'pos_phone_qty'            => $rec['pos_phone_qty'] ?? 0,
                'pos_ip_qty'               => $rec['pos_ip_qty'] ?? 0,
                'pos_ip_demand'            => $rec['pos_ip_demand'] ?? 0,
                'pos_gprs_qty'             => $rec['pos_gprs_qty'] ?? 0,
                'pos_gprs_demand'          => $rec['pos_gprs_demand'] ?? 0,
                'has_ip_connectivity'      => $rec['has_ip_connectivity'] ?? false,
                'broken_pos_qty'           => $rec['broken_pos_qty'] ?? 0,
                'cash_register_model_code' => $rec['cash_register_model_code'] ?? null,
                'pos_currency_mlc'         => $rec['pos_currency_mlc'] ?? false,
                'pos_currency_cup'         => $rec['pos_currency_cup'] ?? false,
                'qr_fincimex_mlc'          => $rec['qr_fincimex_mlc'] ?? false,
                'qr_fincimex_cup'          => $rec['qr_fincimex_cup'] ?? false,
                'src_fincimex_mlc'         => $rec['src_fincimex_mlc'] ?? null,
                'src_fincimex_cup'         => $rec['src_fincimex_cup'] ?? null,
                'terminal_id'              => $rec['terminal_id'] ?? null,
            ]
        );

        return $wasNew ? 'created' : 'updated';
    }

    private function salesFloorSnapshot(mixed $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        $sf = SalesFloor::with(['municipio:id,name', 'entity:id,name'])->find((int) $id);
        if (!$sf) {
            return null;
        }

        return ['id' => $sf->id, 'label' => $sf->hierarchyLabel()];
    }
}
