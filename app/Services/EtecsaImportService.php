<?php

namespace App\Services;

use App\Models\ConnectivityRecord;
use App\Models\Department;
use App\Models\EtecsaCuotaDetalle;
use App\Models\EtecsaFactura;
use App\Models\EtecsaLlamada;
use App\Models\EtecsaServicio;
use App\Models\EtecsaTrafico;
use App\Models\SalesFloor;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class EtecsaImportService
{
    /**
     * Genera una vista previa enriquecida a partir del array retornado por EtecsaPdfParser.
     *
     * - Detecta duplicados por pdf_hash.
     * - Conectividad: id_facturacion = N° servicio en catálogo (registros_conectividad).
     * - Telefonía fija: si no hay match de catálogo, empareja el N° con teléfono de pisos_venta.phone
     *   o departamentos.telefono (normalización de dígitos).
     * - Devuelve match_status: matched | unmatched y match_source cuando aplica.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function buildPreview(array $parsed, User $user): array
    {
        // ── Detección de duplicado ──────────────────────────────────────────
        $hash = $parsed['pdf_hash'] ?? null;
        if ($hash && EtecsaFactura::where('pdf_hash', $hash)->exists()) {
            return ['duplicate' => true, 'pdf_hash' => $hash];
        }

        // ── Cabecera de la factura ──────────────────────────────────────────
        $factura = $parsed['factura'];

        // ── Enricher de servicios ───────────────────────────────────────────
        // Pre-carga todos los connectivity records necesarios en una sola query
        $numerosServicio = collect($parsed['servicios'])
            ->pluck('numero_servicio')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $connectivityMap = ConnectivityRecord::query()
            ->whereIn('id_facturacion', $numerosServicio)
            ->with('salesFloor:id,name,entity_id')
            ->get()
            ->keyBy('id_facturacion');

        [$pisoPorTelefono, $deptoPorTelefono] = $this->buildPhoneLocationIndexes();

        $matched   = 0;
        $unmatched = 0;

        $serviciosEnriquecidos = collect($parsed['servicios'])->map(function (array $servicio) use ($connectivityMap, $pisoPorTelefono, $deptoPorTelefono, &$matched, &$unmatched) {
            $num  = $servicio['numero_servicio'] ?? null;
            $conn = $num ? $connectivityMap->get($num) : null;

            if ($conn) {
                $matched++;
                $servicio['match_status']           = 'matched';
                $servicio['match_source']           = EtecsaServicio::MATCH_CONNECTIVITY;
                $servicio['connectivity_record_id'] = $conn->id;
                $servicio['sales_floor_id']         = $conn->sales_floor_id;
                $servicio['department_id']          = null;
                $servicio['sales_floor_name']       = $conn->salesFloor?->name;
                $servicio['department_name']        = null;
                $servicio['tipo_enlace']            = $conn->tipo_enlace;
                $servicio['velocidad_etecsa']       = $conn->velocidad_etecsa;
                $servicio['cuota_catalogo']         = $conn->cuota;
                $servicio['diferencia_cuota']       = round(
                    (float) ($servicio['cuota_facturada'] ?? 0) - (float) ($conn->cuota ?? 0),
                    2
                );
            } else {
                $tel = $num ? $this->matchTelefoniaFijaPorTelefono((string) $num, $pisoPorTelefono, $deptoPorTelefono) : null;
                if ($tel !== null) {
                    $matched++;
                    $servicio['match_status']           = 'matched';
                    $servicio['match_source']           = $tel['match_source'];
                    $servicio['connectivity_record_id'] = null;
                    $servicio['sales_floor_id']         = $tel['sales_floor_id'];
                    $servicio['department_id']          = $tel['department_id'];
                    $servicio['sales_floor_name']       = $tel['sales_floor_name'];
                    $servicio['department_name']        = $tel['department_name'];
                    $servicio['tipo_enlace']            = 'Telefonía fija';
                    $servicio['velocidad_etecsa']       = null;
                    $servicio['cuota_catalogo']         = null;
                    $servicio['diferencia_cuota']       = null;
                } else {
                    $unmatched++;
                    $servicio['match_status']           = 'unmatched';
                    $servicio['match_source']           = null;
                    $servicio['connectivity_record_id'] = null;
                    $servicio['sales_floor_id']         = null;
                    $servicio['department_id']          = null;
                    $servicio['sales_floor_name']       = null;
                    $servicio['department_name']        = null;
                    $servicio['tipo_enlace']            = null;
                    $servicio['velocidad_etecsa']       = null;
                    $servicio['cuota_catalogo']         = null;
                    $servicio['diferencia_cuota']       = null;
                }
            }

            return $servicio;
        })->all();

        return [
            'duplicate' => false,
            'pdf_hash'  => $hash,
            'factura'   => $factura,
            'servicios' => $serviciosEnriquecidos,
            'resumen'   => [
                'total'     => count($serviciosEnriquecidos),
                'matched'   => $matched,
                'unmatched' => $unmatched,
            ],
        ];
    }

    /**
     * @return array{0: array<string, SalesFloor>, 1: array<string, Department>}
     */
    private function buildPhoneLocationIndexes(): array
    {
        $pisoPorTelefono = [];
        foreach (SalesFloor::query()->whereNotNull('phone')->where('phone', '!=', '')->get(['id', 'name', 'phone', 'entity_id']) as $sf) {
            foreach (PhoneNormalizer::matchKeys($sf->phone) as $key) {
                if (! isset($pisoPorTelefono[$key])) {
                    $pisoPorTelefono[$key] = $sf;
                }
            }
        }

        $deptoPorTelefono = [];
        foreach (Department::query()->whereNotNull('telefono')->where('telefono', '!=', '')->get(['id', 'name', 'telefono', 'entity_id']) as $dept) {
            foreach (PhoneNormalizer::matchKeys($dept->telefono) as $key) {
                if (! isset($deptoPorTelefono[$key])) {
                    $deptoPorTelefono[$key] = $dept;
                }
            }
        }

        return [$pisoPorTelefono, $deptoPorTelefono];
    }

    /**
     * Empareja N° de servicio de telefonía fija (PDF) con teléfono de piso u oficina.
     * Prioridad: piso de venta → departamento.
     *
     * @param  array<string, SalesFloor>  $pisoPorTelefono
     * @param  array<string, Department>  $deptoPorTelefono
     * @return array{
     *   match_source: string,
     *   sales_floor_id: int|null,
     *   department_id: int|null,
     *   sales_floor_name: string|null,
     *   department_name: string|null
     * }|null
     */
    private function matchTelefoniaFijaPorTelefono(string $numeroServicio, array $pisoPorTelefono, array $deptoPorTelefono): ?array
    {
        foreach (PhoneNormalizer::matchKeys($numeroServicio) as $key) {
            if (isset($pisoPorTelefono[$key])) {
                $sf = $pisoPorTelefono[$key];

                return [
                    'match_source'      => EtecsaServicio::MATCH_TELEFONIA_PISO,
                    'sales_floor_id'    => $sf->id,
                    'department_id'     => null,
                    'sales_floor_name'  => $sf->name,
                    'department_name'   => null,
                ];
            }
        }

        foreach (PhoneNormalizer::matchKeys($numeroServicio) as $key) {
            if (isset($deptoPorTelefono[$key])) {
                $d = $deptoPorTelefono[$key];

                return [
                    'match_source'      => EtecsaServicio::MATCH_TELEFONIA_DEPARTAMENTO,
                    'sales_floor_id'    => null,
                    'department_id'     => $d->id,
                    'sales_floor_name'  => null,
                    'department_name'   => $d->name,
                ];
            }
        }

        return null;
    }

    /**
     * Persiste la factura con todos sus servicios, cuotas, tráfico y llamadas.
     *
     * Debe recibir el array devuelto por buildPreview (con los servicios enriquecidos).
     * Los servicios con connectivity_record_id null se guardan con numero_servicio crudo.
     *
     * @param  array<string, mixed>  $previewData
     */
    public function apply(array $previewData, User $user): EtecsaFactura
    {
        return DB::transaction(function () use ($previewData, $user): EtecsaFactura {

            // ── 1. Crear cabecera ────────────────────────────────────────────
            $facturaData           = $previewData['factura'];
            $facturaData['pdf_hash']    = $previewData['pdf_hash'] ?? null;
            $facturaData['imported_by'] = $user->id;

            $factura = EtecsaFactura::create($facturaData);

            // ── 2. Acumuladores para insert masivo ───────────────────────────
            $allCuotas  = [];
            $allTrafico = [];
            $allLlamadas = [];
            $now = now()->toDateTimeString();

            // Caché de servicios ya creados en esta transacción:
            // clave → "conn:{connectivity_record_id}" o "num:{numero_servicio}"
            // valor → EtecsaServicio ya persistido
            $servicioCache = [];

            foreach ($previewData['servicios'] as $svcData) {
                // ── 2a. Crear o acumular servicio ────────────────────────────
                // El mismo connectivity_record_id puede aparecer en múltiples
                // bloques del PDF (e.g. cargo base + cargo extra del mismo enlace).
                // En ese caso acumulamos los importes en lugar de crear duplicados.
                $connId = $svcData['connectivity_record_id'] ?? null;
                $numSvc = $connId === null ? ($svcData['numero_servicio'] ?? null) : null;
                $cacheKey = $connId !== null ? "conn:{$connId}" : "num:{$numSvc}";

                if (isset($servicioCache[$cacheKey])) {
                    // Acumular sobre el servicio ya existente
                    $servicio = $servicioCache[$cacheKey];
                    $servicio->increment('cuota_facturada', (float) ($svcData['cuota_facturada'] ?? 0));
                    $servicio->increment('consumo',         (float) ($svcData['consumo']          ?? 0));
                    $servicio->increment('comision',        (float) ($svcData['comision']         ?? 0));
                    $servicio->increment('impuesto',        (float) ($svcData['impuesto']         ?? 0));
                    $servicio->increment('total_servicio',  (float) ($svcData['total_servicio']   ?? 0));
                } else {
                    $servicio = EtecsaServicio::create([
                        'factura_id'             => $factura->id,
                        'connectivity_record_id' => $connId,
                        'sales_floor_id'         => $svcData['sales_floor_id'] ?? null,
                        'department_id'          => $svcData['department_id'] ?? null,
                        'match_source'           => $svcData['match_source'] ?? null,
                        'numero_servicio'        => $numSvc,
                        'cuota_facturada'        => $svcData['cuota_facturada'] ?? 0,
                        'consumo'                => $svcData['consumo'] ?? 0,
                        'comision'               => $svcData['comision'] ?? 0,
                        'impuesto'               => $svcData['impuesto'] ?? 0,
                        'total_servicio'         => $svcData['total_servicio'] ?? 0,
                    ]);
                    $servicioCache[$cacheKey] = $servicio;
                }

                // ── 2b. Cuotas detalle ───────────────────────────────────────
                foreach ($svcData['cuotas_detalle'] ?? [] as $cuota) {
                    $allCuotas[] = [
                        'servicio_id' => $servicio->id,
                        'concepto'    => $cuota['concepto'],
                        'importe'     => $cuota['importe'],
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                // ── 2c. Tráfico ──────────────────────────────────────────────
                foreach ($svcData['trafico'] ?? [] as $traf) {
                    $allTrafico[] = [
                        'servicio_id'  => $servicio->id,
                        'categoria'    => $traf['categoria'],
                        'subcategoria' => $traf['subcategoria'] ?? null,
                        'importe'      => $traf['importe'],
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }

                // ── 2d. Llamadas (sin timestamps) ────────────────────────────
                foreach ($svcData['llamadas'] ?? [] as $llamada) {
                    $allLlamadas[] = [
                        'servicio_id' => $servicio->id,
                        'fecha'       => $llamada['fecha']   ?? null,
                        'hora'        => $llamada['hora']    ?? null,
                        'lugar'       => $llamada['lugar']   ?? null,
                        'destino'     => $llamada['destino'] ?? null,
                        'duracion'    => $llamada['duracion'] ?? null,
                        'importe'     => $llamada['importe']  ?? 0,
                    ];
                }
            }

            // ── 3. Insert masivo ─────────────────────────────────────────────
            if ($allCuotas) {
                EtecsaCuotaDetalle::insert($allCuotas);
            }

            if ($allTrafico) {
                EtecsaTrafico::insert($allTrafico);
            }

            if ($allLlamadas) {
                collect($allLlamadas)
                    ->chunk(500)
                    ->each(fn ($chunk) => EtecsaLlamada::insert($chunk->all()));
            }

            // ── 4. Auditoría ──────────────────────────────────────────────────
            app(AuditService::class)->log(
                'etecsa.factura.importada',
                "Importada factura ETECSA {$factura->numero_factura} — período {$factura->periodo_desde} / {$factura->periodo_hasta}",
                $factura
            );

            return $factura;
        });
    }
}
