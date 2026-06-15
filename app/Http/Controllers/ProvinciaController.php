<?php

namespace App\Http\Controllers;

use App\Models\Provincia;
use App\Support\UserEntityAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProvinciaController extends Controller
{
    public function index(Request $request)
    {
        $provincias = Provincia::withCount('municipios')
            ->when(! UserEntityAccess::bypasses($request->user()), function ($q) use ($request) {
                $ids = UserEntityAccess::allowedProvinciaIds($request->user());
                if ($ids === []) {
                    $q->whereRaw('1 = 0');
                } elseif ($ids !== null) {
                    $q->whereIn('id', $ids);
                }
            })
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Provincias/Index', [
            'provincias' => $provincias,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:provincias,code',
            'active' => 'boolean',
        ]);

        Provincia::create($data);

        return back()->with('success', 'Provincia creada correctamente.');
    }

    public function update(Request $request, Provincia $provincia)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => "required|string|max:10|unique:provincias,code,{$provincia->id}",
            'active' => 'boolean',
        ]);

        $provincia->update($data);

        return back()->with('success', 'Provincia actualizada correctamente.');
    }

    public function destroy(Provincia $provincia)
    {
        $provincia->delete();

        return back()->with('success', 'Provincia eliminada correctamente.');
    }
}
