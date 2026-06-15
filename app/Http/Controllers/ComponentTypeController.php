<?php

namespace App\Http\Controllers;

use App\Models\ComponentType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ComponentTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $types = ComponentType::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->category, fn($q, $c) => $q->where('category', $c))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('ComponentTypes/Index', [
            'types' => $types,
            'filters' => $request->only('search', 'category'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:component_types,name',
            'category' => ['required', Rule::in(['caracteristica', 'periferico', 'dispositivo'])],
            'active' => 'boolean',
        ]);

        $data['slug'] = $this->makeUniqueSlug($data['name']);

        ComponentType::create($data);

        return back()->with('success', 'Tipo de dispositivo creado correctamente.');
    }

    public function update(Request $request, ComponentType $tipo)
    {
        $data = $request->validate([
            'name' => "required|string|max:255|unique:component_types,name,{$tipo->id}",
            'category' => ['required', Rule::in(['caracteristica', 'periferico', 'dispositivo'])],
            'active' => 'boolean',
        ]);

        $data['slug'] = $this->makeUniqueSlug(
            $data['name'],
            $tipo->id,
            Str::slug($data['name']) !== $tipo->slug,
            $tipo->slug
        );

        $tipo->update($data);

        return back()->with('success', 'Tipo de dispositivo actualizado correctamente.');
    }

    public function destroy(ComponentType $tipo)
    {
        $tipo->delete();

        return back()->with('success', 'Tipo de dispositivo eliminado correctamente.');
    }

    private function makeUniqueSlug(
        string $name,
        ?int $ignoreId = null,
        bool $forceRefresh = true,
        ?string $currentSlug = null
    ): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        if (!$forceRefresh && $currentSlug) {
            return $currentSlug;
        }

        while (
            ComponentType::query()
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
