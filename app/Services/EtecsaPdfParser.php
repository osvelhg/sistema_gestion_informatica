<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Parser de Facturas de Servicios ETECSA.
 *
 * Soporta dos tipos de factura:
 *   - Tipo A (telefonía fija): servicios 4xxxxxxx / 7xxxxxxx
 *     Cuotas + consumo (tráfico local, LDN, cargos misceláneos)
 *   - Tipo B (conectividad/ADSL/IP): servicios 9xxxxxxx
 *     Solo cuotas fijas (Acceso a Internet por ADSL / Conectividad IP)
 */
class EtecsaPdfParser
{
    // ─── Punto de entrada público ─────────────────────────────────────────────

    /**
     * Parsea un PDF de ETECSA y retorna un array estructurado normalizado.
     *
     * @param  string $pdfPath  Ruta absoluta al archivo PDF
     * @return array{
     *   factura: array,
     *   servicios: array,
     *   pdf_hash: string
     * }
     *
     * @throws RuntimeException si el PDF no tiene la estructura esperada
     */
    public function parse(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new RuntimeException("Archivo PDF no encontrado: {$pdfPath}");
        }

        $hash = hash_file('sha256', $pdfPath);

        $parser = new Parser();
        $pdf    = $parser->parseFile($pdfPath);
        $text   = $pdf->getText();

        if (empty(trim($text))) {
            throw new RuntimeException('El PDF no contiene texto extraíble. Posiblemente es una imagen escaneada.');
        }

        // Normalizar saltos de línea y eliminar caracteres de control raros
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        $cabecera  = $this->extractCabecera($text);
        $totales   = $this->extractTotales($text);
        $tipo      = $this->detectTipoFactura($text);
        $servicios = $this->extractServicios($text, $tipo);

        return [
            'factura'   => array_merge($cabecera, $totales, ['tipo_factura' => $tipo]),
            'servicios' => $servicios,
            'pdf_hash'  => $hash,
        ];
    }

    // ─── Extracción de cabecera ────────────────────────────────────────────────

    private function extractCabecera(string $text): array
    {
        $cabecera = [];

        // Número de cliente
        if (preg_match('/Numero de Cliente:\s*(\S+)/i', $text, $m)) {
            $cabecera['numero_cliente'] = trim($m[1]);
        } else {
            throw new RuntimeException('No se encontró el Número de Cliente en el PDF.');
        }

        // Número de factura
        if (preg_match('/No\.Factura:\s*(\S+)/i', $text, $m)) {
            $cabecera['numero_factura'] = trim($m[1]);
        } else {
            throw new RuntimeException('No se encontró el Número de Factura en el PDF.');
        }

        // Nombre del cliente: primera línea(s) antes de la dirección / "Ave." / "Ctra."
        // El nombre ocupa la(s) primera(s) líneas del encabezado
        if (preg_match('/^(.+?)(?:\nAve\.|Ctra\.|Calle\s|\nZona Postal)/s', $text, $m)) {
            $cabecera['nombre_cliente'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        } else {
            $cabecera['nombre_cliente'] = '';
        }

        // Periodo de consumo
        if (preg_match('/Periodo de Consumo:\s*(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/i', $text, $m)) {
            $cabecera['periodo_desde'] = $this->toDate($m[1]);
            $cabecera['periodo_hasta'] = $this->toDate($m[2]);
        } else {
            throw new RuntimeException('No se encontró el Periodo de Consumo en el PDF.');
        }

        // Fecha de vencimiento
        if (preg_match('/Fecha de Vencimiento:\s*(\d{2}\/\d{2}\/\d{4})/i', $text, $m)) {
            $cabecera['fecha_vencimiento'] = $this->toDate($m[1]);
        }

        // Código de pago en banco
        if (preg_match('/Codigo de Pago en Banco:\s*(\S+)/i', $text, $m)) {
            $cabecera['codigo_pago_banco'] = trim($m[1]);
        }

        // Moneda
        if (preg_match('/\nMoneda:\s*(\w+)/i', $text, $m)) {
            $cabecera['moneda'] = trim($m[1]);
        } else {
            $cabecera['moneda'] = 'CUP';
        }

        // Tasa de cambio (puede no aparecer)
        if (preg_match('/Tasa:\s*([\d,\.]+)/i', $text, $m)) {
            $cabecera['tasa_cambio'] = $this->toFloat($m[1]);
        }

        // Oficina comercial
        if (preg_match('/Oficina Comercial:\s*(.+)/i', $text, $m)) {
            $cabecera['oficina_comercial'] = trim($m[1]);
        }

        // Zona postal
        if (preg_match('/Zona Postal:\s*(\S+)/i', $text, $m)) {
            $cabecera['zona_postal'] = trim($m[1]);
        }

        return $cabecera;
    }

    // ─── Extracción de totales ─────────────────────────────────────────────────

    private function extractTotales(string $text): array
    {
        $totales = [
            'total_cuota_mensual' => 0,
            'total_consumo'       => 0,
            'total_comision'      => 0,
            'total_impuesto'      => 0,
            'total_facturado'     => 0,
            'total_saldo'         => 0,
            'total_a_pagar'       => 0,
            'total_usd'           => null,
        ];

        // La fila de totales tiene el patrón:
        // [número] [número] [número] [número] [número] [número] [número]
        // Cuota Mensual Consumo Comision Impuesto Facturado Saldo Total a Pagar
        // Se busca la fila NUMÉRICA debajo del encabezado
        if (preg_match(
            '/Cuota Mensual\s+Consumo\s+Comision\s+Impuesto\s+Facturado\s+Saldo\s+Total a Pagar\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d,\.]+)\s+([\d,\.]+)/i',
            $text,
            $m
        )) {
            $totales['total_cuota_mensual'] = $this->toFloat($m[1]);
            $totales['total_consumo']       = $this->toFloat($m[2]);
            $totales['total_comision']      = $this->toFloat($m[3]);
            $totales['total_impuesto']      = $this->toFloat($m[4]);
            $totales['total_facturado']     = $this->toFloat($m[5]);
            $totales['total_saldo']         = $this->toFloat($m[6]);
            $totales['total_a_pagar']       = $this->toFloat($m[7]);
        }

        // Total en USD: "A Pagar: 1,319.32"
        if (preg_match('/A Pagar:\s*([\d,\.]+)/i', $text, $m)) {
            $totales['total_usd'] = $this->toFloat($m[1]);
        }

        return $totales;
    }

    // ─── Detección del tipo de factura ────────────────────────────────────────

    private function detectTipoFactura(string $text): string
    {
        $hasConectividad = (bool) preg_match('/Acceso a Internet por ADSL|Conectividad IP/i', $text);
        $hasTelefonia    = (bool) preg_match('/Valor Basico|Trafico Local|Larga Distancia Nacional/i', $text);

        if ($hasConectividad && $hasTelefonia) {
            return 'mixta';
        }
        if ($hasConectividad) {
            return 'conectividad';
        }
        return 'telefonia';
    }

    // ─── Segmentación y parsing de servicios ─────────────────────────────────

    /**
     * Divide el texto por bloques de servicio y parsea cada uno.
     */
    private function extractServicios(string $text, string $tipo): array
    {
        // Marcador de inicio de cada bloque de servicio
        // Patrón: "Servicio: XXXXXXXX" (con posibles espacios)
        $bloques = preg_split('/(?=Servicio:\s*\d{8,})/i', $text);

        $servicios = [];

        foreach ($bloques as $bloque) {
            $bloque = trim($bloque);

            // El bloque debe comenzar con "Servicio: XXXXXXXX"
            if (!preg_match('/^Servicio:\s*(\d+)/i', $bloque, $m)) {
                continue;
            }

            $numeroServicio = trim($m[1]);
            $servicio       = $this->parseServiceBlock($bloque, $numeroServicio, $tipo);

            if ($servicio !== null) {
                $servicios[] = $servicio;
            }
        }

        if (empty($servicios)) {
            throw new RuntimeException('No se encontraron servicios en el PDF. Verifique el formato del archivo.');
        }

        return $servicios;
    }

    /**
     * Parsea un bloque de texto correspondiente a un único servicio.
     */
    private function parseServiceBlock(string $bloque, string $numeroServicio, string $tipoFactura): ?array
    {
        $cuotas = $this->parseCuotaMensual($bloque);
        $trafico = $this->parseTraficoLocal($bloque);
        $llamadas = $this->parseLlamadas($bloque);
        $cargosMisc = $this->parseCargosMisc($bloque);

        $cuotaFacturada = array_reduce($cuotas, fn ($acc, $i) => $acc + (float) ($i['importe'] ?? 0), 0.0);
        $consumoTrafico = array_reduce($trafico, fn ($acc, $i) => $acc + (float) ($i['importe'] ?? 0), 0.0);
        $consumoLlamadas = array_reduce($llamadas, fn ($acc, $i) => $acc + (float) ($i['importe'] ?? 0), 0.0);
        $consumo = $consumoTrafico + $consumoLlamadas;
        $comision = 0.0;
        $impuesto = 0.0;
        foreach ($cargosMisc as $cargo) {
            $sub = mb_strtolower(trim((string) ($cargo['subcategoria'] ?? '')));
            $imp = (float) ($cargo['importe'] ?? 0);
            if ($sub !== '' && str_contains($sub, 'impuest')) {
                $impuesto += $imp;
                continue;
            }
            if ($sub !== '' && str_contains($sub, 'comision')) {
                $comision += $imp;
                continue;
            }
            // Cargos misceláneos no clasificados se contabilizan como consumo.
            $consumo += $imp;
        }
        $totalServicio = $cuotaFacturada + $consumo + $comision + $impuesto;

        return [
            'numero_servicio' => $numeroServicio,
            'cuota_facturada' => round($cuotaFacturada, 2),
            'consumo'         => round($consumo, 2),
            'comision'        => round($comision, 2),
            'impuesto'        => round($impuesto, 2),
            'total_servicio'  => round($totalServicio, 2),
            'cuotas_detalle'  => $cuotas,
            'trafico'         => $trafico,
            'llamadas'        => $llamadas,
            'cargos_misc'     => $cargosMisc,
        ];
    }

    // ─── Parsing de cuota mensual ─────────────────────────────────────────────

    /**
     * Extrae las líneas de detalle de cuota mensual de un bloque de servicio.
     *
     * Ejemplo del PDF:
     *   Detalle de Cuota Mensual
     *    Valor Basico                  140.00
     *    Extension del Telefono         36.40
     *                                  176.40   ← subtotal (ignorado)
     */
    private function parseCuotaMensual(string $bloque): array
    {
        $lineas = [];

        $seccion = $bloque;
        if (preg_match('/Detalle\s*(de)?\s*Cuota\s*Mensual(.*?)(?=Trafico Local Metrado|Larga Distancia|Cargos Miscelaneos|Servicio:|Talon de Cobro|$)/si', $bloque, $m)) {
            $seccion = $m[2];
        } elseif (preg_match('/Cuota\s*Mensual(.*?)(?=Trafico Local Metrado|Larga Distancia|Cargos Miscelaneos|Servicio:|Talon de Cobro|$)/si', $bloque, $m)) {
            // Variantes de PDF de conectividad pueden omitir "Detalle de".
            $seccion = $m[1];
        }

        // Cada línea de concepto tiene formato: "  Concepto ...  importe"
        // El subtotal de la sección es un número alineado solo (sin concepto)
        // Los capturamos con su importe
        $conceptosConocidos = [
            'Valor Basico',
            'Extension del Telefono',
            'Candado fijo',
            'Linea directa',
            'Servicio Factura Plus',
            'Acceso a Internet por ADSL',
            'Conectividad IP',
        ];

        foreach ($conceptosConocidos as $concepto) {
            // Buscar el concepto seguido de un importe numérico en la misma línea
            $patron = '/' . preg_quote($concepto, '/') . '\s+([\d,\.]+)/i';
            if (preg_match($patron, $seccion, $m)) {
                $lineas[] = [
                    'concepto' => $concepto,
                    'importe'  => $this->toFloat($m[1]),
                ];
            }
        }

        // Si no encontramos ningún concepto conocido en la sección, intentar en todo el bloque
        if (empty($lineas)) {
            foreach ($conceptosConocidos as $concepto) {
                $patron = '/' . preg_quote($concepto, '/') . '\s+([\d,\.]+)/i';
                if (preg_match($patron, $bloque, $mx)) {
                    $lineas[] = [
                        'concepto' => $concepto,
                        'importe'  => $this->toFloat($mx[1]),
                    ];
                }
            }
        }

        // Si no encontramos ningún concepto conocido, intentar extracción genérica
        if (empty($lineas)) {
            // Líneas con formato "  Texto con mayúscula   número"
            if (preg_match_all('/^\s{2,}([A-ZÁÉÍÓÚ][^\n\d]{3,}?)\s+([\d,\.]{4,})\s*$/m', $seccion, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $concepto = trim($match[1]);
                    $importe  = $this->toFloat($match[2]);
                    if ($importe > 0) {
                        $lineas[] = ['concepto' => $concepto, 'importe' => $importe];
                    }
                }
            }
        }

        return $lineas;
    }

    // ─── Parsing de tráfico local ─────────────────────────────────────────────

    /**
     * Extrae las líneas de tráfico local metrado de un bloque de servicio.
     *
     * Ejemplo del PDF:
     *   Trafico Local Metrado
     *    Trafico Local No Bonificado    78.40
     *    Acceso a Redes Moviles         25.21
     *                                  103.61   ← subtotal (ignorado)
     */
    private function parseTraficoLocal(string $bloque): array
    {
        $lineas = [];

        if (!preg_match('/Trafico Local Metrado(.*?)(?=Larga Distancia Nacional|Cargos Miscelaneos|Servicio:|Talon de Cobro|$)/si', $bloque, $m)) {
            return $lineas;
        }

        $seccion = $m[1];

        $subcategorias = [
            'Trafico Local No Bonificado',
            'Acceso a Redes Moviles',
            'Acceso a Servicio Informacion de Abonados',
        ];

        foreach ($subcategorias as $sub) {
            $patron = '/' . preg_quote($sub, '/') . '\s+([\d,\.]+)/i';
            if (preg_match($patron, $seccion, $match)) {
                $lineas[] = [
                    'categoria'   => 'local_metrado',
                    'subcategoria' => $sub,
                    'importe'     => $this->toFloat($match[1]),
                ];
            }
        }

        return $lineas;
    }

    // ─── Parsing de llamadas de Larga Distancia Nacional ─────────────────────

    /**
     * Extrae el detalle de llamadas LDN de un bloque de servicio.
     *
     * Formato de tabla en el PDF:
     *   Fecha      Hora       Lugar           Destino    Duracion   Importe
     *   02/02/2026 15:33:21   C. HABANA       72613879   00:00:10   1.05
     */
    private function parseLlamadas(string $bloque): array
    {
        $llamadas = [];

        if (!preg_match('/Larga Distancia Nacional(.*?)(?=Cargos Miscelaneos|Trafico Local Metrado|Servicio:|Talon de Cobro|\d{3,}\.\d{2}\s*$)/si', $bloque, $m)) {
            return $llamadas;
        }

        $seccion = $m[1];

        // Patrón de fila de llamada:
        // DD/MM/YYYY HH:MM:SS  LUGAR  NUMERO  HH:MM:SS  importe
        $patron = '/(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2}:\d{2})\s+(.+?)\s+(\d{6,})\s+(\d{2}:\d{2}:\d{2})\s+([\d,\.]+)/';

        if (preg_match_all($patron, $seccion, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $llamadas[] = [
                    'fecha'    => $this->toDate($match[1]),
                    'hora'     => $match[2],
                    'lugar'    => trim($match[3]),
                    'destino'  => trim($match[4]),
                    'duracion' => $match[5],
                    'importe'  => $this->toFloat($match[6]),
                ];
            }
        }

        return $llamadas;
    }

    // ─── Parsing de cargos misceláneos ────────────────────────────────────────

    /**
     * Extrae los cargos misceláneos de un bloque de servicio.
     *
     * Ejemplo:
     *   Cargos Miscelaneos
     *    Servicio Hora Exacta    0.35
     */
    private function parseCargosMisc(string $bloque): array
    {
        $cargos = [];

        if (!preg_match('/Cargos Miscelaneos(.*?)(?=Servicio:|Talon de Cobro|$)/si', $bloque, $m)) {
            return $cargos;
        }

        $seccion = $m[1];

        // Líneas con concepto e importe
        if (preg_match_all('/^\s+(.+?)\s+([\d,\.]+)\s*$/m', $seccion, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $concepto = trim($match[1]);
                $importe  = $this->toFloat($match[2]);
                // Filtrar líneas que son solo números (subtotales)
                if (!empty($concepto) && !is_numeric(str_replace([',', '.'], '', $concepto)) && $importe > 0) {
                    $cargos[] = [
                        'categoria'    => 'cargos_miscelaneos',
                        'subcategoria' => $concepto,
                        'importe'      => $importe,
                    ];
                }
            }
        }

        return $cargos;
    }

    // ─── Utilidades ───────────────────────────────────────────────────────────

    /**
     * Convierte un string numérico en formato cubano a float.
     * Formato: "1,319.32" → 1319.32  |  "31,663.76" → 31663.76  |  "140.00" → 140.0
     */
    private function toFloat(string $raw): float
    {
        // El formato de ETECSA usa coma como separador de miles y punto como decimal
        // Ejemplo: "1,319.32" → eliminar comas → "1319.32"
        $clean = str_replace(',', '', trim($raw));
        return (float) $clean;
    }

    /**
     * Convierte fecha de formato dd/mm/yyyy a Y-m-d.
     */
    private function toDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }
}
