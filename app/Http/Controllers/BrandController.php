<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brands = Brand::withCount('componentModels')
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'active' => 'boolean',
        ]);

        Brand::create($data);

        return back()->with('success', 'Marca creada correctamente.');
    }

    public function update(Request $request, Brand $marca)
    {
        $data = $request->validate([
            'name' => "required|string|max:255|unique:brands,name,{$marca->id}",
            'active' => 'boolean',
        ]);

        $marca->update($data);

        return back()->with('success', 'Marca actualizada correctamente.');
    }

    public function destroy(Brand $marca)
    {
        $marca->delete();
        return back()->with('success', 'Marca eliminada correctamente.');
    }
}
