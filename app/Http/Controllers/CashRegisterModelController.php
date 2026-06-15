<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterModelController extends Controller
{
    public function index(Request $request): Response
    {
        $models = CashRegisterModel::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('code')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('CashRegisterModels/Index', [
            'models' => $models,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|integer|min:1|max:99|unique:modelos_caja,code',
            'name' => 'required|string|max:255',
            'active' => 'boolean',
        ]);
        CashRegisterModel::create($data);
        return back()->with('success', 'Modelo de caja creado.');
    }

    public function update(Request $request, CashRegisterModel $modeloCaja): RedirectResponse
    {
        $data = $request->validate([
            'code' => "required|integer|min:1|max:99|unique:modelos_caja,code,{$modeloCaja->id}",
            'name' => 'required|string|max:255',
            'active' => 'boolean',
        ]);
        $modeloCaja->update($data);
        return back()->with('success', 'Modelo de caja actualizado.');
    }

    public function destroy(CashRegisterModel $modeloCaja): RedirectResponse
    {
        $modeloCaja->delete();
        return back()->with('success', 'Modelo de caja eliminado.');
    }
}
