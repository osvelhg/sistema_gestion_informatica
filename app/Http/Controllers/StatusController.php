<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $statuses = Status::when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('order')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Statuses/Index', [
            'statuses' => $statuses,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:statuses,name',
            'color' => 'required|string|max:20',
            'active' => 'boolean',
            'order' => 'integer',
        ]);

        Status::create($data);

        return back()->with('success', 'Estado creado correctamente.');
    }

    public function update(Request $request, Status $estado)
    {
        $data = $request->validate([
            'name' => "required|string|max:255|unique:statuses,name,{$estado->id}",
            'color' => 'required|string|max:20',
            'active' => 'boolean',
            'order' => 'integer',
        ]);

        $estado->update($data);

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    public function destroy(Status $estado)
    {
        $estado->delete();
        return back()->with('success', 'Estado eliminado correctamente.');
    }
}
