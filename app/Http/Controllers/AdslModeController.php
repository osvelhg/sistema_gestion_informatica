<?php

namespace App\Http\Controllers;

use App\Models\AdslMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdslModeController extends Controller
{
    public function index(Request $request): Response
    {
        $modes = AdslMode::query()
            ->when($request->search, fn ($q, $s) => $q->where('code', 'ilike', "%{$s}%")
                ->orWhere('nombre', 'ilike', "%{$s}%"))
            ->orderBy('code')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('AdslModes/Index', [
            'modes'   => $modes,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AdslMode::create($this->validateData($request));

        return back()->with('success', 'Modo ADSL creado.');
    }

    public function update(Request $request, AdslMode $modoAdsl): RedirectResponse
    {
        $modoAdsl->update($this->validateData($request, $modoAdsl->id));

        return back()->with('success', 'Modo ADSL actualizado.');
    }

    public function destroy(AdslMode $modoAdsl): RedirectResponse
    {
        $modoAdsl->delete();

        return back()->with('success', 'Modo ADSL eliminado.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'   => 'required|string|max:20|unique:modos_adsl,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'nombre' => 'required|string|max:100',
            'activo' => 'boolean',
        ]);
    }
}
