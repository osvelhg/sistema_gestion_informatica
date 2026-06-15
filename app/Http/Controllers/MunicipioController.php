<?php

namespace App\Http\Controllers;

use App\Models\Municipio;
use App\Models\Provincia;
use App\Support\UserEntityAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MunicipioController extends Controller
{
    public function index(Request $request)
    {
        $municipios = Municipio::with('provincia')
            ->when(! UserEntityAccess::bypasses($request->user()), function ($q) use ($request) {
                $ids = UserEntityAccess::allowedMunicipioIds($request->user());
                if ($ids === []) {
                    $q->whereRaw('1 = 0');
                } elseif ($ids !== null) {
                    $q->whereIn('id', $ids);
                }
            })
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->provincia_id, fn($q, $id) => $q->where('provincia_id', $id))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Municipios/Index', [
            'municipios' => $municipios,
            'provincias' => Provincia::active()->orderBy('name')
                ->when(! UserEntityAccess::bypasses($request->user()), function ($q) use ($request) {
                    $ids = UserEntityAccess::allowedProvinciaIds($request->user());
                    if ($ids === []) {
                        $q->whereRaw('1 = 0');
                    } elseif ($ids !== null) {
                        $q->whereIn('id', $ids);
                    }
                })
                ->get(['id', 'name']),
            'filters' => $request->only('search', 'provincia_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provincia_id' => 'required|exists:provincias,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'active' => 'boolean',
        ]);

        $data['provincia_code'] = Provincia::find($data['provincia_id'])?->code;

        Municipio::create($data);

        return back()->with('success', 'Municipio creado correctamente.');
    }

    public function update(Request $request, Municipio $municipio)
    {
        $data = $request->validate([
            'provincia_id' => 'required|exists:provincias,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'active' => 'boolean',
        ]);

        $data['provincia_code'] = Provincia::find($data['provincia_id'])?->code;

        $municipio->update($data);

        return back()->with('success', 'Municipio actualizado correctamente.');
    }

    public function destroy(Municipio $municipio)
    {
        $municipio->delete();

        return back()->with('success', 'Municipio eliminado correctamente.');
    }

    public function byProvincia(Request $request, Provincia $provincia)
    {
        if (! UserEntityAccess::bypasses($request->user())) {
            $allowed = UserEntityAccess::allowedProvinciaIds($request->user());
            if ($allowed !== null && ! in_array($provincia->id, $allowed, true)) {
                return response()->json([], 403);
            }
        }

        $q = $provincia->municipios()->active()->orderBy('name');
        if (! UserEntityAccess::bypasses($request->user())) {
            $mIds = UserEntityAccess::allowedMunicipioIds($request->user());
            if ($mIds === []) {
                $q->whereRaw('1 = 0');
            } elseif ($mIds !== null) {
                $q->whereIn('id', $mIds);
            }
        }

        return response()->json($q->get(['id', 'name']));
    }
}
