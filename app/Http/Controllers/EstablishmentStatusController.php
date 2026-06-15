<?php

namespace App\Http\Controllers;

use App\Models\EstablishmentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EstablishmentStatusController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('EstablishmentStatuses/Index', [
            'statuses' => EstablishmentStatus::orderBy('name')->paginate($this->perPage($request))->withQueryString(),
            'filters'  => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EstablishmentStatus::create($this->validateData($request));
        return back()->with('success', 'Estado de establecimiento creado.');
    }

    public function update(Request $request, EstablishmentStatus $estadoEstablecimiento): RedirectResponse
    {
        $estadoEstablecimiento->update($this->validateData($request));
        return back()->with('success', 'Estado de establecimiento actualizado.');
    }

    public function destroy(EstablishmentStatus $estadoEstablecimiento): RedirectResponse
    {
        $estadoEstablecimiento->delete();
        return back()->with('success', 'Estado de establecimiento eliminado.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'   => 'required|string|max:100',
            'active' => 'boolean',
        ]);
    }
}
