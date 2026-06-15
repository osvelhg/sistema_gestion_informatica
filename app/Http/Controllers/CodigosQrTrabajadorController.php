<?php

namespace App\Http\Controllers;

use App\Models\DatacellSource;
use App\Models\RolQr;
use App\Models\Trabajador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CodigosQrTrabajadorController extends Controller
{
    public function index(DatacellSource $source): Response
    {
        $asignaciones = $source->trabajadores()
            ->with('municipio')
            ->orderBy('nombre')
            ->get()
            ->map(function ($t) {
                return [
                    'pivot_id'    => $t->pivot->id,
                    'trabajador_id' => $t->id,
                    'nombre'      => $t->nombre,
                    'ci'          => $t->ci,
                    'telefono'    => $t->telefono,
                    'rolqr_id'    => $t->pivot->rolqr_id,
                    'rolqr_nombre'=> optional(RolQr::find($t->pivot->rolqr_id))->nombre,
                    'fecha_alta'  => $t->pivot->fecha_alta,
                    'fecha_baja'  => $t->pivot->fecha_baja,
                    'estado'      => $t->pivot->estado,
                ];
            });

        return Inertia::render('CodigosQR/Trabajadores/Index', [
            'source'      => $source->only('id', 'source', 'source_name'),
            'asignaciones' => $asignaciones,
        ]);
    }

    public function create(DatacellSource $source): Response
    {
        return Inertia::render('CodigosQR/Trabajadores/Create', [
            'source'      => $source->only('id', 'source', 'source_name'),
            'trabajadores' => Trabajador::orderBy('nombre')->get(['id', 'nombre', 'ci']),
            'roles'       => RolQr::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request, DatacellSource $source): RedirectResponse
    {
        $request->validate([
            'trabajadores'              => 'required|array|min:1',
            'trabajadores.*.trabajador_id' => 'required|exists:trabajadores,id',
            'trabajadores.*.rolqr_id'   => 'nullable|exists:roles_qr,id',
            'trabajadores.*.fecha_alta' => 'required|date',
        ]);

        foreach ($request->trabajadores as $row) {
            // Avoid duplicates: update if already assigned
            $existing = $source->trabajadores()
                ->wherePivot('trabajador_id', $row['trabajador_id'])
                ->first();

            if ($existing) {
                $source->trabajadores()->updateExistingPivot($row['trabajador_id'], [
                    'rolqr_id'   => $row['rolqr_id'] ?? null,
                    'fecha_alta' => $row['fecha_alta'],
                    'estado'     => true,
                ]);
            } else {
                $source->trabajadores()->attach($row['trabajador_id'], [
                    'rolqr_id'   => $row['rolqr_id'] ?? null,
                    'fecha_alta' => $row['fecha_alta'],
                    'estado'     => true,
                ]);
            }
        }

        return redirect()->route('codigos-qr.trabajadores.index', $source->id)
            ->with('success', 'Trabajadores asignados correctamente.');
    }

    public function edit(DatacellSource $source, int $pivotId): Response
    {
        $pivot = \DB::table('fuente_trabajador')->where('id', $pivotId)->firstOrFail();
        $trabajador = Trabajador::findOrFail($pivot->trabajador_id);

        return Inertia::render('CodigosQR/Trabajadores/Edit', [
            'source'     => $source->only('id', 'source', 'source_name'),
            'asignacion' => [
                'pivot_id'    => $pivot->id,
                'trabajador_id' => $trabajador->id,
                'nombre'      => $trabajador->nombre,
                'ci'          => $trabajador->ci,
                'rolqr_id'    => $pivot->rolqr_id,
                'fecha_alta'  => $pivot->fecha_alta,
                'fecha_baja'  => $pivot->fecha_baja,
                'estado'      => (bool) $pivot->estado,
            ],
            'roles' => RolQr::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, DatacellSource $source, int $pivotId): RedirectResponse
    {
        $request->validate([
            'rolqr_id'   => 'nullable|exists:roles_qr,id',
            'fecha_alta' => 'required|date',
            'fecha_baja' => 'nullable|date|after_or_equal:fecha_alta',
            'estado'     => 'boolean',
        ]);

        \DB::table('fuente_trabajador')
            ->where('id', $pivotId)
            ->where('source_id', $source->id)
            ->update([
                'rolqr_id'   => $request->rolqr_id,
                'fecha_alta' => $request->fecha_alta,
                'fecha_baja' => $request->fecha_baja,
                'estado'     => $request->boolean('estado'),
                'updated_at' => now(),
            ]);

        return redirect()->route('codigos-qr.trabajadores.index', $source->id)
            ->with('success', 'Asignación actualizada.');
    }

    public function destroy(DatacellSource $source, int $pivotId): RedirectResponse
    {
        \DB::table('fuente_trabajador')
            ->where('id', $pivotId)
            ->where('source_id', $source->id)
            ->delete();

        return redirect()->route('codigos-qr.trabajadores.index', $source->id)
            ->with('success', 'Asignación eliminada.');
    }
}
