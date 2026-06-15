<?php

namespace App\Http\Controllers;

use App\Models\CanalElectronico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CanalElectronicoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Nomencladores/CanalElectronico/Index', [
            'canales' => CanalElectronico::withTrashed()
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'datacell_id' => 'nullable|integer|unique:canal_electronicos,datacell_id',
            'estado'      => 'boolean',
        ]);

        CanalElectronico::create($data);

        return back()->with('success', 'Canal Electrónico creado.');
    }

    public function update(Request $request, CanalElectronico $canalElectronico): RedirectResponse
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'datacell_id' => "nullable|integer|unique:canal_electronicos,datacell_id,{$canalElectronico->id}",
            'estado'      => 'boolean',
        ]);

        $canalElectronico->restore(); // por si estaba soft-deleted
        $canalElectronico->update($data);

        return back()->with('success', 'Canal Electrónico actualizado.');
    }

    public function destroy(CanalElectronico $canalElectronico): RedirectResponse
    {
        $canalElectronico->delete();
        return back()->with('success', 'Canal Electrónico eliminado.');
    }
}
