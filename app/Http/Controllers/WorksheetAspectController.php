<?php

namespace App\Http\Controllers;

use App\Models\WorksheetAspect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorksheetAspectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WorksheetAspects/Index', [
            'aspects' => WorksheetAspect::ordered()->get(),
            'sections' => [
                'equipamiento' => 'Revisión de Equipamiento',
                'software'     => 'Revisión de Software y Sistemas',
                'salvas'       => 'Salvas',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        WorksheetAspect::create($this->validateData($request));
        return back()->with('success', 'Aspecto creado correctamente.');
    }

    public function update(Request $request, WorksheetAspect $aspectoHoja): RedirectResponse
    {
        $aspectoHoja->update($this->validateData($request));
        return back()->with('success', 'Aspecto actualizado correctamente.');
    }

    public function destroy(WorksheetAspect $aspectoHoja): RedirectResponse
    {
        $aspectoHoja->delete();
        return back()->with('success', 'Aspecto eliminado correctamente.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'section' => 'required|in:equipamiento,software,salvas',
            'label'   => 'required|string|max:500',
            'order'   => 'required|integer|min:0',
            'active'  => 'boolean',
        ]);
    }
}
