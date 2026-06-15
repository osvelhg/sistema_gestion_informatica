<?php

namespace App\Http\Controllers;

use App\Models\TipoFuente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoFuenteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Nomencladores/TipoFuente/Index', [
            'tipos' => TipoFuente::withTrashed()
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'datacell_id' => 'nullable|integer|unique:tipo_fuentes,datacell_id',
            'estado'      => 'boolean',
        ]);

        TipoFuente::create($data);

        return back()->with('success', 'Tipo de Fuente creado.');
    }

    public function update(Request $request, TipoFuente $tipoFuente): RedirectResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'datacell_id' => "nullable|integer|unique:tipo_fuentes,datacell_id,{$tipoFuente->id}",
            'estado'      => 'boolean',
        ]);

        $tipoFuente->restore();
        $tipoFuente->update($data);

        return back()->with('success', 'Tipo de Fuente actualizado.');
    }

    public function destroy(TipoFuente $tipoFuente): RedirectResponse
    {
        $tipoFuente->delete();
        return back()->with('success', 'Tipo de Fuente eliminado.');
    }
}
