<?php

namespace App\Http\Controllers;

use App\Models\EstablishmentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EstablishmentTypeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('EstablishmentTypes/Index', [
            'types'   => EstablishmentType::orderBy('name')->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EstablishmentType::create($this->validateData($request));
        return back()->with('success', 'Tipo de establecimiento creado.');
    }

    public function update(Request $request, EstablishmentType $tipoEstablecimiento): RedirectResponse
    {
        $tipoEstablecimiento->update($this->validateData($request));
        return back()->with('success', 'Tipo de establecimiento actualizado.');
    }

    public function destroy(EstablishmentType $tipoEstablecimiento): RedirectResponse
    {
        $tipoEstablecimiento->delete();
        return back()->with('success', 'Tipo de establecimiento eliminado.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'   => 'required|string|max:100',
            'active' => 'boolean',
        ]);
    }
}
