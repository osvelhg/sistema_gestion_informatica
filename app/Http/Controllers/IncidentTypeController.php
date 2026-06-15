<?php

namespace App\Http\Controllers;

use App\Models\IncidentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IncidentTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $types = IncidentType::query()
            ->when($request->search, fn($query, $search) => $query->where('name', 'ilike', "%{$search}%"))
            ->withCount('seals')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('IncidentTypes/Index', [
            'types' => $types,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:incident_types,name',
            'active' => 'boolean',
        ]);

        $data['slug'] = $this->makeUniqueSlug($data['name']);

        IncidentType::create($data);

        return back()->with('success', 'Tipo de incidencia creado correctamente.');
    }

    public function update(Request $request, IncidentType $tipoIncidencia): RedirectResponse
    {
        $data = $request->validate([
            'name' => "required|string|max:255|unique:incident_types,name,{$tipoIncidencia->id}",
            'active' => 'boolean',
        ]);

        $data['slug'] = $this->makeUniqueSlug($data['name'], $tipoIncidencia->id);

        $tipoIncidencia->update($data);

        return back()->with('success', 'Tipo de incidencia actualizado correctamente.');
    }

    public function destroy(IncidentType $tipoIncidencia): RedirectResponse
    {
        $tipoIncidencia->delete();

        return back()->with('success', 'Tipo de incidencia eliminado correctamente.');
    }

    private function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while (
            IncidentType::query()
                ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
