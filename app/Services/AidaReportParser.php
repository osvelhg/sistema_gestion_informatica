<?php

namespace App\Services;

/**
 * Parser de informes AIDA64 Business v5.95 (Report Wizard, formato TXT).
 *
 * Tolerante a variaciones de idioma (es/en), prioriza DMI sobre Summary
 * para placa/serial, descarta placeholders OEM y filtra periféricos virtuales.
 */
class AidaReportParser
{
    // Valores OEM que no aportan información real
    private const OEM_PLACEHOLDERS = [
        'to be filled by o.e.m.',
        'to be filled by o.e.m',
        'default string',
        'system serial number',
        'system product name',
        'system manufacturer',
        'system version',
        'unknown',
        'not applicable',
        'none',
        '0x00000000',
        'n/a',
        '-',
    ];

    // Impresoras virtuales que no se deben importar como periférico
    private const VIRTUAL_PRINTERS = [
        'microsoft print to pdf',
        'microsoft xps document writer',
        'onenote',
        'fax',
        'adobe pdf',
        'send to onenote',
        'bullzip',
        'cutepdf',
        'dopdf',
    ];

    // -------------------------------------------------------------------------
    // Punto de entrada
    // -------------------------------------------------------------------------

    public function parse(string $content): array
    {
        $content = $this->normalizeEncoding($content);
        $sections = $this->extractSections($content);

        $header  = $this->parseHeader($sections['header'] ?? '');
        $summary = $this->parseSummary($sections['summary'] ?? '');
        $dmi     = $this->parseDmi($sections['summary'] ?? '');   // DMI está dentro del bloque Summary
        $unknown = $this->parseDebugUnknown($sections['debug_unknown'] ?? '');

        return $this->buildPayload($header, $summary, $dmi, $unknown);
    }

    // -------------------------------------------------------------------------
    // Extracción de secciones
    // -------------------------------------------------------------------------

    private function normalizeEncoding(string $content): string
    {
        // Intentar detectar Windows-1252 y convertir a UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            if ($converted !== false) {
                return $converted;
            }
        }
        return $content;
    }

    private function extractSections(string $content): array
    {
        $sections = [
            'header'        => '',
            'summary'       => '',
            'debug_unknown' => '',
        ];

        // Dividir por encabezados de sección: --------[ Nombre ]---...
        $pattern = '/^--------\[\s*([^\]]+?)\s*\]-+/m';
        $parts   = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!$parts) {
            return $sections;
        }

        // La primera parte es el header (antes de la primera sección)
        $sections['header'] = $parts[0] ?? '';

        // Las partes vienen en pares: [nombre_seccion, contenido]
        for ($i = 1; $i < count($parts) - 1; $i += 2) {
            $name = strtolower(trim($parts[$i]));
            $body = $parts[$i + 1] ?? '';

            if ($name === 'summary') {
                $sections['summary'] = $body;
            } elseif (str_contains($name, 'debug - unknown') || str_contains($name, 'debug unknown')) {
                $sections['debug_unknown'] = $body;
            }
        }

        return $sections;
    }

    // -------------------------------------------------------------------------
    // Parser del encabezado (antes de Summary)
    // -------------------------------------------------------------------------

    private function parseHeader(string $block): array
    {
        $fields = $this->extractKeyValues($block);
        return [
            'computer_name' => $this->sanitize($fields['computer'] ?? null),
            'os'            => $this->sanitize($fields['operating system'] ?? null),
            'generator'     => $this->sanitize($fields['generator'] ?? null),
            'report_date'   => $this->sanitize($fields['date'] ?? null),
        ];
    }

    // -------------------------------------------------------------------------
    // Parser del bloque Summary (incluye DMI al final)
    // -------------------------------------------------------------------------

    private function parseSummary(string $block): array
    {
        $fields = $this->extractKeyValues($block);

        return [
            // Computer
            'os'            => $this->sanitize($fields['operating system'] ?? null),
            'computer_name' => $this->sanitize($fields['computer name'] ?? null),
            'computer_type' => $this->sanitize($fields['computer type'] ?? null),

            // Motherboard
            'cpu_type'          => $this->sanitize($fields['cpu type'] ?? null),
            'motherboard_name'  => $this->sanitize($fields['motherboard name'] ?? null),
            'system_memory'     => $this->sanitize($fields['system memory'] ?? null),
            'bios_type'         => $this->sanitize($fields['bios type'] ?? null),

            // Display
            'monitor'       => $this->sanitize($fields['monitor'] ?? null),
            'video_adapter' => $this->sanitize($fields['video adapter'] ?? null),
            'accelerator_3d'=> $this->sanitize($fields['3d accelerator'] ?? null),

            // Storage
            'disk_drive'    => $this->sanitize($fields['disk drive'] ?? null),

            // Input
            'keyboard'      => $this->sanitize($fields['keyboard'] ?? null),
            'mouse'         => $this->sanitize($fields['mouse'] ?? null),

            // Network
            'primary_ip'    => $this->sanitize($fields['primary ip address'] ?? null),
            'primary_mac'   => $this->sanitize($fields['primary mac address'] ?? null),

            // Printers (puede haber múltiples)
            'printers'      => $fields['__printers__'] ?? [],
        ];
    }

    private function parseDmi(string $block): array
    {
        $fields = $this->extractKeyValues($block);

        return [
            'motherboard_manufacturer' => $this->sanitize($fields['dmi motherboard manufacturer'] ?? null),
            'motherboard_product'      => $this->sanitize($fields['dmi motherboard product'] ?? null),
            'motherboard_version'      => $this->sanitize($fields['dmi motherboard version'] ?? null),
            'motherboard_serial'       => $this->sanitize($fields['dmi motherboard serial number'] ?? null),
            'system_manufacturer'      => $this->sanitize($fields['dmi system manufacturer'] ?? null),
            'system_product'           => $this->sanitize($fields['dmi system product'] ?? null),
            'system_serial'            => $this->sanitize($fields['dmi system serial number'] ?? null),
            'system_uuid'              => $this->sanitize($fields['dmi system uuid'] ?? null),
            'chassis_type'             => $this->sanitize($fields['dmi chassis type'] ?? null),
        ];
    }

    private function parseDebugUnknown(string $block): array
    {
        if (empty(trim($block))) {
            return [];
        }

        $fields = $this->extractKeyValues($block);
        return [
            'monitor_model' => $this->sanitize($fields['monitor model'] ?? null),
            'monitor_id'    => $this->sanitize($fields['monitor id'] ?? null),
        ];
    }

    // -------------------------------------------------------------------------
    // Extracción genérica de key-value del formato AIDA64
    // -------------------------------------------------------------------------

    private function extractKeyValues(string $block): array
    {
        $result   = [];
        $printers = [];
        $lines    = explode("\n", $block);

        foreach ($lines as $line) {
            // Formato: 4+ espacios + clave + 2+ espacios + valor
            // Cubre tanto el Summary (6 espacios) como Debug-Unknown (4 espacios)
            if (!preg_match('/^ {4,}(\S[^\t]+?)\s{2,}(\S.*)$/', $line, $m)) {
                continue;
            }

            $key   = strtolower(trim($m[1]));
            $value = trim($m[2]);

            if (empty($value)) {
                continue;
            }

            if ($key === 'printer') {
                $printers[] = $value;
            } else {
                // Si la misma clave aparece varias veces, quedarse con la primera
                // (p.ej. "Disk Drive" múltiple → primera es la principal)
                if (!array_key_exists($key, $result)) {
                    $result[$key] = $value;
                }
            }
        }

        $result['__printers__'] = $printers;
        return $result;
    }

    // -------------------------------------------------------------------------
    // Construcción del payload SGI
    // -------------------------------------------------------------------------

    private function buildPayload(array $header, array $summary, array $dmi, array $unknown): array
    {
        // --- Tipo de equipo ---
        $chassisType = $dmi['chassis_type'] ?? '';
        $equipType   = $this->detectEquipmentType($chassisType, $summary['computer_type'] ?? '');

        // --- Placa madre: DMI tiene prioridad sobre Summary ---
        $moboManufacturer = $dmi['motherboard_manufacturer'];
        $moboProduct      = $dmi['motherboard_product'] ?? null;
        if (empty($moboProduct)) {
            // Fallback a Summary solo si no es "Unknown"
            $summaryMobo = $summary['motherboard_name'] ?? null;
            if ($summaryMobo && !str_contains(strtolower($summaryMobo), 'unknown')) {
                $moboProduct = $summaryMobo;
            }
        }
        $moboSerial = $dmi['motherboard_serial'];

        // --- CPU ---
        $cpu       = $this->parseCpu($summary['cpu_type'] ?? '');

        // --- RAM ---
        $ramModel  = $this->parseRam($summary['system_memory'] ?? '');

        // --- HDD ---
        $hddModel  = $this->parseHdd($summary['disk_drive'] ?? '');

        // --- Monitor: Summary > Debug-Unknown ---
        $monitors  = [];
        $monSummary = $summary['monitor'] ?? null;
        if ($monSummary && !str_contains(strtolower($monSummary), 'pnp gen') && !str_contains(strtolower($monSummary), '[nodb]')) {
            $monitors[] = $this->parseMonitor($monSummary);
        } elseif (!empty($unknown['monitor_model'])) {
            // Debug - Unknown tiene el modelo real (p.ej. dagmara: S22e-20)
            $monitors[] = [
                'component_type_slug' => 'monitor',
                'brand'               => null,
                'model'               => $unknown['monitor_model'],
                'serial_number'       => null,
            ];
        } elseif ($monSummary) {
            // Usar el genérico si es lo único disponible
            $monitors[] = $this->parseMonitor($monSummary);
        }

        // --- Periféricos: teclado, ratón, impresoras ---
        $perifericos = $monitors;

        if (!empty($summary['keyboard'])) {
            $perifericos[] = [
                'component_type_slug' => 'keyboard',
                'brand'               => null,
                'model'               => $summary['keyboard'],
                'serial_number'       => null,
            ];
        }

        if (!empty($summary['mouse'])) {
            $perifericos[] = [
                'component_type_slug' => 'mouse',
                'brand'               => null,
                'model'               => $summary['mouse'],
                'serial_number'       => null,
            ];
        }

        foreach ($this->filterPrinters($summary['printers'] ?? []) as $printerModel) {
            $perifericos[] = [
                'component_type_slug' => 'printer',
                'brand'               => null,
                'model'               => $printerModel,
                'serial_number'       => null,
            ];
        }

        // --- OS para meta ---
        $os = $summary['os'] ?? $header['os'] ?? null;

        return [
            'equipment' => [
                'type'    => $equipType,
                'chassis' => $moboProduct,
            ],
            'components' => [
                'motherboard'  => [
                    'brand'         => $moboManufacturer,
                    'model'         => $moboProduct,
                    'serial_number' => $moboSerial,
                    'inventory_number' => null,
                    'status'        => null,
                ],
                'cpu' => [
                    'brand'         => $cpu['brand'],
                    'model'         => $cpu['model'],
                    'serial_number' => null,
                    'inventory_number' => null,
                    'status'        => null,
                ],
                'ram' => [
                    'brand'         => null,
                    'model'         => $ramModel,
                    'serial_number' => null,
                    'inventory_number' => null,
                    'status'        => null,
                ],
                'hdd' => [
                    'brand'         => null,
                    'model'         => $hddModel,
                    'serial_number' => null,
                    'inventory_number' => null,
                    'status'        => null,
                ],
                'power_supply' => ['brand' => null, 'model' => null, 'serial_number' => null, 'inventory_number' => null, 'status' => null],
                'reader'       => ['brand' => null, 'model' => null, 'serial_number' => null, 'inventory_number' => null, 'status' => null],
            ],
            'perifericos' => $perifericos,
            'dispositivos' => [],
            'meta' => [
                'os'            => $os,
                'computer_name' => $summary['computer_name'] ?? $header['computer_name'] ?? null,
                'ip'            => $summary['primary_ip'] ?? null,
                'mac'           => $summary['primary_mac'] ?? null,
                'bios_date'     => $this->extractBiosDate($summary['bios_type'] ?? null),
                'uuid'          => $dmi['system_uuid'] ?? null,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Parsers específicos
    // -------------------------------------------------------------------------

    /**
     * "HexaCore Intel Core i5-8400, 3800 MHz (38 x 100)" → {brand: "Intel", model: "Core i5-8400"}
     * "DualCore Intel Celeron G1840, 2800 MHz" → {brand: "Intel", model: "Celeron G1840"}
     * "Intel(R) Core(TM) i5-4460 CPU @ 3.20GHz" → {brand: "Intel", model: "Core i5-4460"}
     */
    private function parseCpu(string $raw): array
    {
        if (empty($raw)) {
            return ['brand' => null, 'model' => null];
        }

        // Formato AIDA: "[Cores] Intel/AMD Model, Freq MHz"
        if (preg_match('/(?:Single|Dual|Quad|Hexa|Octa|Deca)?Core\s+(Intel|AMD|ARM)\s+(.+?),\s*[\d.]+\s*MHz/i', $raw, $m)) {
            return [
                'brand' => trim($m[1]),
                'model' => trim($m[2]),
            ];
        }

        // Formato genérico Windows: "Intel(R) Core(TM) i5-4460 CPU @ 3.20GHz"
        if (preg_match('/(Intel|AMD|ARM)/i', $raw, $bm)) {
            $brand = ucfirst(strtolower($bm[1]));
            // Limpiar el modelo: quitar "(R)", "(TM)", "CPU", "@ freq"
            $model = preg_replace('/\(R\)|\(TM\)|CPU\s+@[\s\d.GHzMHz]+/i', '', $raw);
            $model = preg_replace('/\s{2,}/', ' ', trim($model));
            return ['brand' => $brand, 'model' => $model];
        }

        return ['brand' => null, 'model' => $raw];
    }

    /**
     * "3959 MB  (DDR4 SDRAM)" → "3959 MB DDR4"
     * "1947 MB  (DDR3 SDRAM)" → "1947 MB DDR3"
     */
    private function parseRam(string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        if (preg_match('/(\d+)\s*MB\s*\(?(DDR\d?)\s*/i', $raw, $m)) {
            return "{$m[1]} MB {$m[2]}";
        }

        // Fallback: limpiar paréntesis extra
        return preg_replace('/\s{2,}/', ' ', trim(str_replace(['(', ')'], '', $raw)));
    }

    /**
     * "ST1000DM010-2EP102  (1 TB, 7200 RPM, SATA-III)" → "ST1000DM010-2EP102 (1 TB, SATA-III)"
     * "ATA ST500DM002-1BD142" → "ST500DM002-1BD142"
     */
    private function parseHdd(string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        // Quitar prefijo "ATA "
        $clean = preg_replace('/^ATA\s+/i', '', trim($raw));

        // Extraer modelo + capacidad + interfaz, eliminar RPM (no relevante para inventario)
        if (preg_match('/^(.+?)\s+\((.+?)\)/', $clean, $m)) {
            $model    = trim($m[1]);
            $details  = $m[2];
            // Quitar RPM del detalle
            $details  = preg_replace('/,?\s*\d+\s*RPM/i', '', $details);
            $details  = trim($details, ', ');
            return "{$model} ({$details})";
        }

        return $clean;
    }

    /**
     * "ViewSonic VA2055 Series  [19.5" LCD]  (UD3162340134)"
     * → {brand: "ViewSonic", model: "VA2055 Series [19.5\" LCD]", serial: "UD3162340134"}
     *
     * "Monitor PnP genérico [NoDB]  (V5GRK308)"
     * → {brand: null, model: "Monitor PnP genérico", serial: "V5GRK308"}
     */
    private function parseMonitor(string $raw): array
    {
        if (empty($raw)) {
            return ['component_type_slug' => 'monitor', 'brand' => null, 'model' => null, 'serial_number' => null];
        }

        $serial = null;
        $model  = $raw;

        // Extraer serial entre últimos paréntesis
        if (preg_match('/\(([^)]{4,})\)\s*$/', $raw, $sm)) {
            $serial = $sm[1];
            $model  = trim(substr($raw, 0, strrpos($raw, '(')));
        }

        // Limpiar [NoDB] y espacios dobles
        $model = preg_replace('/\s*\[NoDB\]/i', '', $model);
        $model = preg_replace('/\s{2,}/', ' ', trim($model));

        // Intentar extraer marca (primera palabra si el modelo no es genérico)
        $brand = null;
        if (!preg_match('/^Monitor\s+PnP/i', $model)) {
            $words = explode(' ', $model, 2);
            if (count($words) > 1 && strlen($words[0]) > 2) {
                $brand = $words[0];
                $model = $words[1];
            }
        }

        return [
            'component_type_slug' => 'monitor',
            'brand'               => $brand,
            'model'               => $model ?: null,
            'serial_number'       => $serial,
        ];
    }

    /**
     * Filtra impresoras virtuales, devuelve solo las reales con nombre limpio.
     */
    private function filterPrinters(array $printers): array
    {
        $real = [];
        foreach ($printers as $printer) {
            $lower = strtolower(trim($printer));
            if (empty($lower)) {
                continue;
            }

            $isVirtual = false;
            foreach (self::VIRTUAL_PRINTERS as $virtual) {
                if (str_starts_with($lower, $virtual)) {
                    $isVirtual = true;
                    break;
                }
            }

            if (!$isVirtual) {
                // Limpiar rutas UNC: \\server\printer (name) → printer (name)
                $clean = preg_replace('/^\\\\\\\\.+?\\\\/', '', $printer);
                $real[] = trim($clean);
            }
        }

        return $real;
    }

    /**
     * "AMI (05/24/2019)" → "05/24/2019"
     */
    private function extractBiosDate(?string $biosType): ?string
    {
        if (empty($biosType)) {
            return null;
        }

        if (preg_match('/\((\d{2}\/\d{2}\/\d{4})\)/', $biosType, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * "Desktop Case" / "Tower" → "PC"
     * "Notebook" / "Laptop" → "Laptop"
     */
    private function detectEquipmentType(string $chassisType, string $computerType): string
    {
        $lower = strtolower($chassisType . ' ' . $computerType);

        if (str_contains($lower, 'notebook') || str_contains($lower, 'laptop') || str_contains($lower, 'portable')) {
            return 'Laptop';
        }

        return 'PC';
    }

    /**
     * Devuelve null si el valor es un placeholder OEM o vacío.
     */
    private function sanitize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $lower = strtolower(trim($value));

        foreach (self::OEM_PLACEHOLDERS as $placeholder) {
            if ($lower === $placeholder) {
                return null;
            }
        }

        return trim($value);
    }
}
