<?php

namespace App\Http\Controllers;

use App\Models\EtecsaFactura;
use App\Models\EtecsaServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EtecsaDashboardController extends Controller
{
    public function __invoke(Request $request): InertiaResponse
    {
        // ── KPIs del último período importado ─────────────────────────────────
        $ultimaFactura = EtecsaFactura::orderByDesc('periodo_desde')->first();

        $kpis = [
            'total_a_pagar'     => $ultimaFactura?->total_a_pagar ?? 0,
            'total_facturado'   => $ultimaFactura?->total_facturado ?? 0,
            'total_usd'         => $ultimaFactura?->total_usd,
            'periodo'           => $ultimaFactura
                ? ($ultimaFactura->periodo_desde?->format('d/m/Y') . ' — ' . $ultimaFactura->periodo_hasta?->format('d/m/Y'))
                : null,
            'numero_factura'    => $ultimaFactura?->numero_factura,
        ];

        // Servicios activos en el último período
        $kpis['servicios_activos'] = $ultimaFactura
            ? $ultimaFactura->servicios()->count()
            : 0;

        // ── Variación respecto al período anterior ────────────────────────────
        $facturaAnterior = EtecsaFactura::orderByDesc('periodo_desde')->skip(1)->first();
        $kpis['variacion'] = ($ultimaFactura && $facturaAnterior)
            ? round(
                (float) $ultimaFactura->total_a_pagar - (float) $facturaAnterior->total_a_pagar,
                2
            )
            : null;

        // ── Evolución mensual (últimos 12 períodos) ───────────────────────────
        $evolucion = EtecsaFactura::query()
            ->select('periodo_desde', 'tipo_factura', 'total_a_pagar', 'total_cuota_mensual', 'total_consumo')
            ->orderByDesc('periodo_desde')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        // ── Top 10 servicios más costosos (último período) ────────────────────
        $topServicios = $ultimaFactura
            ? $ultimaFactura->servicios()
                ->with([
                    'connectivityRecord:id,id_facturacion,tipo_enlace,sales_floor_id',
                    'connectivityRecord.salesFloor:id,name',
                ])
                ->orderByDesc('total_servicio')
                ->limit(10)
                ->get()
                ->map(fn ($s) => [
                    'numero_servicio' => $s->numero_servicio_efectivo,
                    'tipo'            => $s->tipo_servicio,
                    'piso'            => $s->salesFloor?->name,
                    'cuota'           => $s->cuota_facturada,
                    'consumo'         => $s->consumo,
                    'total'           => $s->total_servicio,
                ])
            : [];

        // ── Desglose por Piso de Venta (último período) ───────────────────────
        $porPiso = $ultimaFactura
            ? EtecsaServicio::query()
                ->where('factura_id', $ultimaFactura->id)
                ->join('registros_conectividad as rc', 'rc.id', '=', 'etecsa_servicios.connectivity_record_id')
                ->join('pisos_venta as pv', 'pv.id', '=', 'rc.sales_floor_id')
                ->groupBy('rc.sales_floor_id', 'pv.name')
                ->selectRaw('pv.name as piso_name, SUM(etecsa_servicios.total_servicio) as total, COUNT(*) as servicios')
                ->orderByDesc('total')
                ->get()
            : collect();

        return Inertia::render('EtecsaFacturacion/Dashboard', [
            'kpis'      => $kpis,
            'evolucion' => $evolucion,
            'topServicios' => $topServicios,
            'porPiso'   => $porPiso,
        ]);
    }
}
