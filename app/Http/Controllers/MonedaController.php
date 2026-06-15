<?php

namespace App\Http\Controllers;

use App\Models\Moneda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonedaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Nomencladores/Moneda/Index', [
            'monedas' => Moneda::withTrashed()->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'sigla'       => 'required|string|max:10|unique:monedas,sigla',
            'simbolo'     => 'nullable|string|max:10',
            'tasa_cambio' => 'required|numeric|min:0',
            'estado'      => 'boolean',
        ]);

        $data['sigla'] = strtoupper($data['sigla']);
        Moneda::create($data);

        return back()->with('success', 'Moneda creada.');
    }

    public function update(Request $request, Moneda $moneda): RedirectResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'sigla'       => "required|string|max:10|unique:monedas,sigla,{$moneda->id}",
            'simbolo'     => 'nullable|string|max:10',
            'tasa_cambio' => 'required|numeric|min:0',
            'estado'      => 'boolean',
        ]);

        $data['sigla'] = strtoupper($data['sigla']);
        $moneda->restore();
        $moneda->update($data);

        return back()->with('success', 'Moneda actualizada.');
    }

    public function destroy(Moneda $moneda): RedirectResponse
    {
        $moneda->delete();
        return back()->with('success', 'Moneda eliminada.');
    }
}
