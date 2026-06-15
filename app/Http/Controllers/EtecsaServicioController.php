<?php

namespace App\Http\Controllers;

use App\Models\EtecsaServicio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EtecsaServicioController extends Controller
{
    public function show(EtecsaServicio $servicio, Request $request): InertiaResponse
    {
        $servicio->load([
            'factura:id,numero_factura,periodo_desde,periodo_hasta',
            'connectivityRecord:id,id_facturacion,tipo_enlace,velocidad_etecsa,cuota,sales_floor_id',
            'connectivityRecord.salesFloor:id,name,entity_id',
            'connectivityRecord.salesFloor.entity:id,name,code',
            'salesFloorDirect:id,name,entity_id',
            'salesFloorDirect.entity:id,name,code',
            'department:id,name,entity_id',
            'department.entity:id,name,code',
            'cuotasDetalle',
            'trafico',
        ]);

        // Llamadas paginadas server-side (50 por página)
        $llamadas = $servicio->llamadas()
            ->orderBy('fecha')
            ->orderBy('hora')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('EtecsaFacturacion/ServicioDetalle', [
            'servicio' => array_merge($servicio->toArray(), [
                'numero_servicio_efectivo' => $servicio->numero_servicio_efectivo,
                'tipo_servicio'            => $servicio->tipo_servicio,
                'descripcion_servicio'     => $servicio->descripcion_servicio,
                'diferencia_cuota'         => $servicio->connectivity_record_id ? $servicio->diferencia : null,
                'ubicacion_label'          => $servicio->ubicacion_label,
            ]),
            'llamadas' => $llamadas,
        ]);
    }
}
