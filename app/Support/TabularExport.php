<?php

namespace App\Support;

use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class TabularExport
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function download(
        string $format,
        string $title,
        array $headers,
        array $rows,
        string $filenamePrefix,
    ): Response {
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            $format = 'csv';
        }
        // If PhpSpreadsheet is not installed in target env, avoid fatal errors.
        if ($format === 'xlsx' && ! class_exists(Spreadsheet::class)) {
            $format = 'csv';
        }

        $headerCount = count($headers);
        $normalizeText = static function (mixed $v): string {
            $s = $v === null ? '' : (string) $v;
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

            return $clean === false ? '' : $clean;
        };
        $normalizedRows = array_map(function ($row) use ($headerCount, $normalizeText) {
            $row = array_map(fn ($v) => $normalizeText($v), array_values($row));
            while (count($row) < $headerCount) {
                $row[] = '';
            }

            return array_slice($row, 0, $headerCount);
        }, $rows);

        $ts = now()->format('Ymd_His');

        if ($format === 'csv') {
            return response()->streamDownload(
                function () use ($headers, $normalizedRows, $normalizeText) {
                    $output = fopen('php://output', 'w');
                    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                    fputcsv($output, array_map($normalizeText, $headers));
                    foreach ($normalizedRows as $row) {
                        fputcsv($output, $row);
                    }
                    fclose($output);
                },
                "{$filenamePrefix}_{$ts}.csv",
                ['Content-Type' => 'text/csv; charset=UTF-8']
            );
        }

        if ($format === 'xlsx') {
            $downloadName = "{$filenamePrefix}_{$ts}.xlsx";
            $tempPath = tempnam(sys_get_temp_dir(), 'sgi_xlsx_');
            if ($tempPath === false) {
                throw new \RuntimeException('No se pudo crear el archivo temporal para Excel.');
            }

            try {
                $spreadsheet = new Spreadsheet;
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Exportacion');

                $colCount = max(count($headers), 1);
                $lastCol = Coordinate::stringFromColumnIndex($colCount);

                $layout = Branding::resolvedLayout();
                $org = $layout['organization_name'];
                $sys = $layout['system_name'];
                $docTitle = $layout['header_title'];
                $footerLeft = $layout['footer_left'];
                $footerRight = $layout['footer_right'];
                $generatedAt = now()->format('d/m/Y H:i');

                $sheet->setCellValue('A1', $org);
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A2', $sys);
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A3', $docTitle);
                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle('A3')->getFont()->getColor()->setRGB('1D4ED8');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A4', $title);
                $sheet->mergeCells("A4:{$lastCol}4");
                $sheet->getStyle('A4')->getFont()->setBold(true);
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $headerRow = 6;
                $sheet->fromArray([array_map($normalizeText, $headers)], null, 'A'.$headerRow);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $firstDataRow = $headerRow + 1;
                if ($normalizedRows !== []) {
                    $sheet->fromArray($normalizedRows, null, 'A'.$firstDataRow);
                }
                $tableEndRow = $headerRow + count($normalizedRows);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$tableEndRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$tableEndRow}")->getBorders()->getAllBorders()
                    ->getColor()->setRGB('CBD5E1');

                for ($c = 1; $c <= $colCount; $c++) {
                    $colLetter = Coordinate::stringFromColumnIndex($c);
                    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                }

                $footerStartRow = $tableEndRow + 2;
                $sheet->setCellValue('A'.$footerStartRow, $footerLeft);
                $sheet->mergeCells("A{$footerStartRow}:{$lastCol}{$footerStartRow}");
                $sheet->getStyle('A'.$footerStartRow)->getFont()->setSize(9);
                $sheet->getStyle('A'.$footerStartRow)->getFont()->getColor()->setRGB('64748B');
                $sheet->getStyle('A'.$footerStartRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $footerSecondRow = $footerStartRow + 1;
                $sheet->setCellValue('A'.$footerSecondRow, $footerRight.' - '.$generatedAt);
                $sheet->mergeCells("A{$footerSecondRow}:{$lastCol}{$footerSecondRow}");
                $sheet->getStyle('A'.$footerSecondRow)->getFont()->setSize(9);
                $sheet->getStyle('A'.$footerSecondRow)->getFont()->getColor()->setRGB('64748B');
                $sheet->getStyle('A'.$footerSecondRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $writer = new Xlsx($spreadsheet);
                $writer->save($tempPath);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $writer);
            } catch (\Throwable $e) {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }

                throw $e;
            }

            return response()->download($tempPath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        $pdf = Pdf::loadView('pdf.tabular_export', [
            'title'              => $title,
            'headers'            => array_map($normalizeText, $headers),
            'rows'               => $normalizedRows,
            'branding'           => SystemSetting::branding(),
            'brandingLogoDataUrl'=> SystemSetting::logoDataUrl(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filenamePrefix}_{$ts}.pdf");
    }
}
