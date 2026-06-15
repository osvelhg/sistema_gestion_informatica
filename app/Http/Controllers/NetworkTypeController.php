<?php

namespace App\Http\Controllers;

use App\Models\NetworkType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NetworkTypeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('NetworkTypes/Index', [
            'types' => NetworkType::orderBy('name')->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        NetworkType::create($this->validateData($request));
        return back()->with('success', 'Tipo de red creado.');
    }

    public function update(Request $request, NetworkType $tipoRed): RedirectResponse
    {
        $tipoRed->update($this->validateData($request));
        return back()->with('success', 'Tipo de red actualizado.');
    }

    public function destroy(NetworkType $tipoRed): RedirectResponse
    {
        $tipoRed->delete();
        return back()->with('success', 'Tipo de red eliminado.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'   => 'required|string|max:100',
            'color'  => 'nullable|string|max:30',
            'active' => 'boolean',
        ]);
    }
}
