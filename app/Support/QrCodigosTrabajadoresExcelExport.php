<?php

namespace App\Support;

use App\Models\DatacellSource;
use App\Models\RolQr;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrCodigosTrabajadoresExcelExport
{
    /**
     * @param  Collection<int, DatacellSource>  $sources
     */
    public static function download(Collection $sources): StreamedResponse
    {
        $rolNombres = self::resolveRolNombres($sources);

        $spreadsheet = new Spreadsheet;
        $resumen = $spreadsheet->getActiveSheet();
        $resumen->setTitle('Resumen');
        $sheetTitles = ['Resumen' => true];

        $row = 1;
        $resumen->setCellValue('A'.$row, 'Codigos QR sin trabajadores asignados');
        $resumen->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
        $row += 2;

        $headersSin = ['Source', 'Nombre visible', 'Unidad / piso', 'Codigo Golden', 'Canal', 'Tipo', 'Moneda', 'Activo'];
        foreach ($headersSin as $c => $h) {
            $col = chr(65 + $c);
            $resumen->setCellValue($col.$row, $h);
            $resumen->getStyle($col.$row)->getFont()->setBold(true);
            $resumen->getStyle($col.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2E8F0');
        }
        $row++;

        $sinTrabajadores = $sources->filter(fn (DatacellSource $s) => $s->trabajadores->isEmpty())->values();
        foreach ($sinTrabajadores as $s) {
            $resumen->setCellValue('A'.$row, $s->source);
            $resumen->setCellValue('B'.$row, (string) ($s->source_name ?? ''));
            $resumen->setCellValue('C'.$row, (string) ($s->salesFloor?->name ?? ''));
            $resumen->setCellValue('D'.$row, (string) ($s->salesFloor?->codigo_golden ?? ''));
            $resumen->setCellValue('E'.$row, (string) ($s->canalElectronico?->nombre ?? ''));
            $resumen->setCellValue('F'.$row, (string) ($s->tipoFuente?->nombre ?? ''));
            $resumen->setCellValue('G'.$row, (string) ($s->moneda ?? ''));
            $resumen->setCellValue('H'.$row, $s->activo ? 'Si' : 'No');
            $row++;
        }

        if ($sinTrabajadores->isEmpty()) {
            $resumen->setCellValue('A'.$row, '(Ningun codigo sin asignaciones en este filtro.)');
            $row++;
        }

        $row += 2;
        $resumen->setCellValue('A'.$row, 'Trabajadores asignados (vista consolidada)');
        $resumen->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
        $row += 2;

        $headersCon = [
            'Source QR', 'Nombre QR', 'Unidad', 'Trabajador', 'CI', 'Telefono',
            'Rol QR', 'Fecha alta', 'Fecha baja', 'Estado asignacion',
        ];
        $colsCon = range('A', 'J');
        foreach ($headersCon as $c => $h) {
            $col = $colsCon[$c];
            $resumen->setCellValue($col.$row, $h);
            $resumen->getStyle($col.$row)->getFont()->setBold(true);
            $resumen->getStyle($col.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DCFCE7');
        }
        $row++;

        foreach ($sources as $s) {
            foreach ($s->trabajadores as $t) {
                $p = $t->pivot;
                $rolId = $p->rolqr_id ?? null;
                $rolKey = $rolId !== null && $rolId !== '' ? (int) $rolId : null;
                $rolNombre = $rolKey !== null ? ($rolNombres[$rolKey] ?? '') : '';

                $resumen->setCellValue('A'.$row, $s->source);
                $resumen->setCellValue('B'.$row, (string) ($s->source_name ?? ''));
                $resumen->setCellValue('C'.$row, (string) ($s->salesFloor?->name ?? ''));
                $resumen->setCellValue('D'.$row, $t->nombre);
                $resumen->setCellValue('E'.$row, (string) ($t->ci ?? ''));
                $resumen->setCellValue('F'.$row, (string) ($t->telefono ?? ''));
                $resumen->setCellValue('G'.$row, $rolNombre);
                $resumen->setCellValue('H'.$row, self::fmtDate($p->fecha_alta ?? null));
                $resumen->setCellValue('I'.$row, self::fmtDate($p->fecha_baja ?? null));
                $resumen->setCellValue('J'.$row, self::pivotEstadoActivo($p->estado) ? 'Activo' : 'Inactivo');
                $row++;
            }
        }

        if ($sources->sum(fn (DatacellSource $s) => $s->trabajadores->count()) === 0) {
            $resumen->setCellValue('A'.$row, '(No hay asignaciones en los codigos del filtro actual.)');
        }

        foreach (range('A', 'J') as $col) {
            $resumen->getColumnDimension($col)->setAutoSize(true);
        }

        foreach ($sources as $s) {
            if ($s->trabajadores->isEmpty()) {
                continue;
            }

            $baseTitle = 'QR_'.$s->id.'_'.self::sanitizeSheetFragment($s->source);
            $title = self::uniqueSheetTitle($baseTitle, $sheetTitles);

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);

            $r = 1;
            $sheet->setCellValue('A'.$r, 'Codigo: '.$s->source);
            $sheet->getStyle('A'.$r)->getFont()->setBold(true);
            $r++;
            $sheet->setCellValue('A'.$r, 'Nombre: '.($s->source_name !== null && $s->source_name !== '' ? $s->source_name : '-'));
            $r++;
            $sheet->setCellValue('A'.$r, 'Unidad: '.($s->salesFloor?->name !== null && $s->salesFloor?->name !== '' ? $s->salesFloor->name : '-'));
            $r += 2;

            $hdr = ['Trabajador', 'CI', 'Telefono', 'Rol QR', 'Fecha alta', 'Fecha baja', 'Estado'];
            foreach ($hdr as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col.$r, $h);
                $sheet->getStyle($col.$r)->getFont()->setBold(true);
                $sheet->getStyle($col.$r)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0E7FF');
            }
            $r++;

            foreach ($s->trabajadores as $t) {
                $p = $t->pivot;
                $rolId = $p->rolqr_id ?? null;
                $rolKey = $rolId !== null && $rolId !== '' ? (int) $rolId : null;
                $rolNombre = $rolKey !== null ? ($rolNombres[$rolKey] ?? '') : '';

                $sheet->setCellValue('A'.$r, $t->nombre);
                $sheet->setCellValue('B'.$r, (string) ($t->ci ?? ''));
                $sheet->setCellValue('C'.$r, (string) ($t->telefono ?? ''));
                $sheet->setCellValue('D'.$r, $rolNombre);
                $sheet->setCellValue('E'.$r, self::fmtDate($p->fecha_alta ?? null));
                $sheet->setCellValue('F'.$r, self::fmtDate($p->fecha_baja ?? null));
                $sheet->setCellValue('G'.$r, self::pivotEstadoActivo($p->estado) ? 'Activo' : 'Inactivo');
                $r++;
            }

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'codigos_qr_trabajadores_'.now()->format('Ymd_His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  Collection<int, DatacellSource>  $sources
     * @return array<int, string>
     */
    private static function resolveRolNombres(Collection $sources): array
    {
        $ids = $sources->flatMap(function (DatacellSource $s) {
            return $s->trabajadores->pluck('pivot.rolqr_id')->filter(fn ($v) => $v !== null && $v !== '');
        })->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return RolQr::query()->whereIn('id', $ids)->pluck('nombre', 'id')->all();
    }

    private static function fmtDate(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d/m/Y');
        }

        return (string) $v;
    }

    private static function pivotEstadoActivo(mixed $estado): bool
    {
        if ($estado === true || $estado === 1 || $estado === '1') {
            return true;
        }

        return false;
    }

    private static function sanitizeSheetFragment(string $source): string
    {
        $s = preg_replace('/[\[\]\*\?\/\\\\\:]/u', '_', $source) ?? $source;
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return $s !== '' ? $s : 'codigo';
    }

    /**
     * @param  array<string, bool>  $used
     */
    private static function uniqueSheetTitle(string $base, array &$used): string
    {
        $base = self::clampSheetTitle($base);
        if ($base === '') {
            $base = 'Hoja';
        }
        if (! isset($used[$base])) {
            $used[$base] = true;

            return $base;
        }
        $i = 2;
        while ($i < 1000) {
            $suffix = '_'.$i;
            $candidate = self::clampSheetTitle(mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix);
            if (! isset($used[$candidate])) {
                $used[$candidate] = true;

                return $candidate;
            }
            $i++;
        }

        $fallback = self::clampSheetTitle($base.'_'.uniqid());
        $used[$fallback] = true;

        return $fallback;
    }

    private static function clampSheetTitle(string $title): string
    {
        $title = preg_replace('/[\[\]\*\?\/\\\\\:]/u', '_', $title) ?? $title;
        $title = trim($title);
        if (mb_strlen($title) > 31) {
            $title = mb_substr($title, 0, 31);
        }

        return $title;
    }
}
