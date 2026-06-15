<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee plantillas Excel de levantamiento de equipos (Hoja1 o hoja activa).
 */
class LevantamientoEquiposImportParser
{
    public const MAX_ROWS = 2000;

    /**
     * @return array{rows: list<array<string, mixed>>, sheet_name: string, header_row: int}
     */
    public function parse(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $this->resolveSheet($spreadsheet);
        $headerRow = 1;
        $headers = $this->readHeaderRow($sheet, $headerRow);
        $colMap = $this->detectColumns($headers);
        $this->assertRequiredColumns($colMap, $headers);

        $highestRow = min((int) $sheet->getHighestRow(), $headerRow + self::MAX_ROWS);
        $rows = [];

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $row = $this->extractRow($sheet, $r, $colMap);
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $row['_sheet_row'] = $r;
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'sheet_name' => $sheet->getTitle(),
            'header_row' => $headerRow,
        ];
    }

    private function resolveSheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if (mb_strtolower($worksheet->getTitle()) === 'hoja1') {
                return $worksheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @return list<string>
     */
    private function readHeaderRow(Worksheet $sheet, int $row): array
    {
        $highestColumn = $sheet->getHighestColumn($row);
        $n = Coordinate::columnIndexFromString($highestColumn);
        $headers = [];
        for ($c = 1; $c <= $n; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $raw = trim((string) $sheet->getCell($letter.$row)->getFormattedValue());
            $headers[] = $this->scrubUtf8($raw);
        }

        return $headers;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function detectColumns(array $headers): array
    {
        $norm = [];
        foreach ($headers as $i => $h) {
            $norm[$i] = $this->normalizeHeader($h);
        }

        $map = [];

        foreach ($norm as $i => $n) {
            // Solo lectura informativa (no se usa para resolver entidad; eso va por inventario en RODAS).
            if ($n !== '' && ! isset($map['codigo_rodas']) && str_contains($n, 'codigo') && (str_contains($n, 'rodas') || str_contains($n, 'entidad'))) {
                $map['codigo_rodas'] = $i;
            }
            if ($n !== '' && ! isset($map['numero_mb'])) {
                $isMemoriaCol = str_contains($n, 'memoria') || (str_contains($n, 'tipo') && str_contains($n, 'ddr'));
                if (! $isMemoriaCol) {
                    // Sin flag /u en el patron: $n ya esta transliterado a ASCII en normalizeHeader.
                    // "N� MB" / "N� MB" suelen quedar como "n mb" tras iconv.
                    $looksLikeNmb = str_contains($n, 'n mb')
                        || preg_match('/^n[\s.\-]*mb$/', $n)
                        || preg_match('/\b(nro|num)\b.*\bmb\b/', $n);
                    if ((str_contains($n, 'numero') && str_contains($n, 'mb'))
                        || $looksLikeNmb
                        || ((str_contains($n, 'inventario') || str_contains($n, 'inv.')) && ! str_contains($n, 'medio'))) {
                        $map['numero_mb'] = $i;
                    }
                }
            }
            if ($n !== '' && ! isset($map['uc_departamento'])) {
                $isDeptWord = str_contains($n, 'departamento') || str_contains($n, 'depto') || str_contains($n, 'dpto');
                $isUcDept = str_contains($n, 'uc') && ($isDeptWord || str_contains($n, 'unidad'));
                $exclude = str_contains($n, 'tipo') && str_contains($n, 'ddr');
                if (($isDeptWord || $isUcDept) && ! $exclude) {
                    $map['uc_departamento'] = $i;
                }
            }
            if ($n !== '' && ! isset($map['computadora']) && str_starts_with($n, 'computadora')) {
                $map['computadora'] = $i;
            }
        }

        foreach ($norm as $i => $n) {
            if ($n === 'marca' && ! isset($map['marca_placa'])) {
                $map['marca_placa'] = $i;
            }
        }

        $microIdx = null;
        foreach ($norm as $i => $n) {
            if (str_contains($n, 'microprocesador')) {
                $microIdx = $i;
                $map['microprocesador'] = $i;

                break;
            }
        }

        foreach ($norm as $i => $n) {
            if (isset($map['modelo_placa'])) {
                break;
            }
            if ($n !== 'modelo') {
                continue;
            }
            if ($microIdx !== null && $i >= $microIdx) {
                continue;
            }
            $map['modelo_placa'] = $i;

            break;
        }

        foreach ($norm as $i => $n) {
            if (str_contains($n, 'tipo') && str_contains($n, 'ddr')) {
                $map['tipo_memoria_ddr'] = $i;
            }
            if (str_contains($n, 'memoria') && str_contains($n, 'mb') && ! str_contains($n, 'tipo')) {
                $map['memoria_mb'] = $i;
            }
            if (str_contains($n, 'disco') && str_contains($n, 'duro')) {
                $map['disco_gb'] = $i;
            }
            if ((str_contains($n, 'sistema') && str_contains($n, 'operativo')) || str_contains($n, 'ssitema')) {
                $map['sistema_operativo'] = $i;
            }
            if (str_contains($n, 'modelo') && str_contains($n, 'ups')) {
                $map['modelo_ups'] = $i;
            }
            if (str_contains($n, 'observacion')) {
                $map['observacion'] = $i;
            }
        }

        $soIdx = $map['sistema_operativo'] ?? null;
        foreach ($norm as $i => $n) {
            if ($soIdx !== null && $i > $soIdx && $n === 'version' && ! isset($map['version_so'])) {
                $map['version_so'] = $i;

                break;
            }
        }

        return $map;
    }

    private function normalizeHeader(string $h): string
    {
        $h = $this->scrubUtf8($h);
        $h = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $h)));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h);
        if ($ascii !== false && $ascii !== '') {
            $h = strtolower($ascii);
        }

        return $h;
    }

    /**
     * Evita bytes UTF-8 invalidos (p. ej. Excel en Latin-1) que rompen preg_replace/preg_match con /u.
     */
    private function scrubUtf8(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $converted = iconv('UTF-8', 'UTF-8//IGNORE', $s);

        return $converted !== false ? $converted : '';
    }

    /**
     * @param  array<string, int>  $colMap
     * @param  list<string>  $headers
     */
    private function assertRequiredColumns(array $colMap, array $headers): void
    {
        if (isset($colMap['numero_mb'])) {
            return;
        }

        $preview = implode(', ', array_slice(array_filter(array_map('trim', $headers)), 0, 12));

        throw new \InvalidArgumentException(
            'No se encontro la columna del numero de inventario (MB). En la primera fila debe existir un titulo como '
            .'"Numero de MB", "N MB / Inventario", etc. Columnas detectadas: '.($preview !== '' ? $preview : '(vacias)').
            '. Compruebe que el encabezado este en la fila 1.'
        );
    }

    /**
     * @param  array<string, int>  $colMap
     * @return array<string, mixed>
     */
    private function extractRow(Worksheet $sheet, int $row, array $colMap): array
    {
        $get = function (string $key) use ($sheet, $row, $colMap): string {
            if (! isset($colMap[$key])) {
                return '';
            }
            $idx = $colMap[$key];
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);

            return trim((string) $sheet->getCell($colLetter.$row)->getFormattedValue());
        };

        $numeroMb = $this->scrubUtf8($get('numero_mb'));
        $numeroMbNorm = $numeroMb !== '' ? preg_replace('/\s+/u', ' ', trim($numeroMb)) : '';

        $marca = $get('marca_placa');
        $modelo = $get('modelo_placa');
        $tipoMem = $get('tipo_memoria_ddr');
        $memMb = $get('memoria_mb');
        $ramModel = trim(implode(' ', array_filter([$tipoMem, $memMb])));

        $so = $get('sistema_operativo');
        $ver = $get('version_so');
        $osFull = trim(implode(' ', array_filter([$so, $ver])));

        $extras = [];
        $ups = $get('modelo_ups');
        $obs = $get('observacion');
        if ($ups !== '') {
            $extras[] = 'UPS: '.$ups;
        }
        if ($obs !== '') {
            $extras[] = $obs;
        }
        $chassisNote = $extras !== [] ? implode(' | ', $extras) : '';

        $caracteristicas = [];
        if ($marca !== '' || $modelo !== '') {
            $caracteristicas[] = [
                'component_type_slug' => 'motherboard',
                'brand' => $marca ?: null,
                'model' => $modelo ?: null,
            ];
        }
        $micro = $get('microprocesador');
        if ($micro !== '') {
            $caracteristicas[] = [
                'component_type_slug' => 'cpu',
                'brand' => null,
                'model' => $micro,
            ];
        }
        if ($ramModel !== '') {
            $caracteristicas[] = [
                'component_type_slug' => 'ram',
                'brand' => null,
                'model' => $ramModel,
            ];
        }
        $disco = $get('disco_gb');
        if ($disco !== '') {
            $caracteristicas[] = [
                'component_type_slug' => 'hdd',
                'brand' => null,
                'model' => $disco,
            ];
        }

        return [
            'codigo_rodas_raw' => $this->scrubUtf8($get('codigo_rodas')),
            'inventory_number' => $numeroMbNorm,
            'uc_departamento' => $this->scrubUtf8($get('uc_departamento')),
            'station_name' => trim($get('computadora')),
            'operating_system' => $osFull !== '' ? $osFull : null,
            'chassis_note' => $chassisNote !== '' ? $chassisNote : null,
            'caracteristicas' => $caracteristicas,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        $inv = trim((string) ($row['inventory_number'] ?? ''));

        return $inv === '';
    }
}
