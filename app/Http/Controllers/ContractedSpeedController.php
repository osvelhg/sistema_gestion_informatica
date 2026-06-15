<?php

namespace App\Http\Controllers;

use App\Models\ContractedSpeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractedSpeedController extends Controller
{
    public function index(Request $request): Response
    {
        $speeds = ContractedSpeed::query()
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'ilike', "%{$s}%"))
            ->orderByRaw('kbps IS NULL, kbps ASC, nombre ASC')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('ContractedSpeeds/Index', [
            'speeds'  => $speeds,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ContractedSpeed::create($this->validateData($request));

        return back()->with('success', 'Velocidad contratada creada.');
    }

    public function update(Request $request, ContractedSpeed $velocidade): RedirectResponse
    {
        $velocidade->update($this->validateData($request, $velocidade->id));

        return back()->with('success', 'Velocidad contratada actualizada.');
    }

    public function destroy(ContractedSpeed $velocidade): RedirectResponse
    {
        $velocidade->delete();

        return back()->with('success', 'Velocidad contratada eliminada.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:50|unique:velocidades_contratadas,nombre' . ($ignoreId ? ",{$ignoreId}" : ''),
            'kbps'   => 'nullable|integer|min:1',
            'activo' => 'boolean',
        ]);
    }
}
