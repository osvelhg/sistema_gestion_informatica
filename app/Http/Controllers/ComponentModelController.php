<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ComponentModel;
use App\Models\ComponentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComponentModelController extends Controller
{
    public function index(Request $request)
    {
        $models = ComponentModel::with(['componentType', 'brand'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->component_type_id, fn($q, $id) => $q->where('component_type_id', $id))
            ->when($request->brand_id, fn($q, $id) => $q->where('brand_id', $id))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('ComponentModels/Index', [
            'models' => $models,
            'componentTypes' => ComponentType::active()->orderBy('name')->get(['id', 'slug', 'name', 'category']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'component_type_id', 'brand_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'component_type_id' => 'required|exists:tipos_componentes,id',
            'brand_id' => 'required|exists:marcas,id',
            'name' => 'required|string|max:255',
            'active' => 'boolean',
        ]);

        ComponentModel::create($data);

        return back()->with('success', 'Modelo creado correctamente.');
    }

    public function update(Request $request, ComponentModel $modelo)
    {
        $data = $request->validate([
            'component_type_id' => 'required|exists:tipos_componentes,id',
            'brand_id' => 'required|exists:marcas,id',
            'name' => 'required|string|max:255',
            'active' => 'boolean',
        ]);

        $modelo->update($data);

        return back()->with('success', 'Modelo actualizado correctamente.');
    }

    public function destroy(ComponentModel $modelo)
    {
        $modelo->delete();
        return back()->with('success', 'Modelo eliminado correctamente.');
    }

    // JSON endpoint: get models by type and optionally brand
    public function byType(ComponentType $componentType, Request $request)
    {
        return response()->json(
            $componentType->componentModels()
                ->with('brand:id,name')
                ->when($request->brand_id, fn($q, $id) => $q->where('brand_id', $id))
                ->active()
                ->orderBy('name')
                ->get(['id', 'brand_id', 'name'])
        );
    }

    // JSON endpoint: get brands that have models for a given type
    public function brandsByType(ComponentType $componentType)
    {
        $brandIds = $componentType->componentModels()->active()->pluck('brand_id')->unique();
        return response()->json(
            Brand::whereIn('id', $brandIds)->active()->orderBy('name')->get(['id', 'name'])
        );
    }
}
