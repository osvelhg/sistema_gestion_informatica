<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Municipio;
use App\Support\UserEntityAccess;
use App\Services\ExternalEntitiesPgService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntityController extends Controller
{
    public function index(Request $request): Response
    {
        $entities = Entity::with('municipio.provincia')
            ->withCount('departments', 'equipmentFiles')
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->tap(fn ($q) => UserEntityAccess::applyToEntitiesQuery($q, $request->user()))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Entities/Index', [
            'entities' => $entities,
            'municipios' => Municipio::with('provincia')->active()->orderBy('name')
                ->when(
                    ($pids = UserEntityAccess::allowedProvinciaIds($request->user())) !== null,
                    fn ($q) => $pids === [] ? $q->whereRaw('1 = 0') : $q->whereIn('provincia_id', $pids)
                )
                ->get(['id', 'name', 'provincia_id']),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:entidades,code',
            'municipio_id' => 'nullable|exists:municipios,id',
            'active' => 'boolean',
        ]);

        if ($data['municipio_id']) {
            $data['municipio_code'] = Municipio::find($data['municipio_id'])?->code;
        }

        Entity::create($data);

        return redirect()->route('entidades.index')->with('success', 'Entidad creada.');
    }

    public function update(Request $request, Entity $entidade): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => "required|string|max:20|unique:entidades,code,{$entidade->id}",
            'municipio_id' => 'nullable|exists:municipios,id',
            'active' => 'boolean',
        ]);

        $data['municipio_code'] = $data['municipio_id']
            ? Municipio::find($data['municipio_id'])?->code
            : null;

        $entidade->update($data);

        return redirect()->route('entidades.index')->with('success', 'Entidad actualizada.');
    }

    public function destroy(Entity $entidade): RedirectResponse
    {
        $entidade->delete();
        return redirect()->route('entidades.index')->with('success', 'Entidad eliminada.');
    }

    public function syncExternal(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('entidades.store'), 403);

        $result = ExternalEntitiesPgService::syncEntities();
        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        if (($result['mode'] ?? '') === 'mappings') {
            $pr = $result['provincias'] ?? ['created' => 0, 'updated' => 0];
            $mu = $result['municipios'] ?? ['created' => 0, 'updated' => 0];
            $en = $result['entities'] ?? ['created' => 0, 'updated_codes' => 0, 'updated_data' => 0];
            $msg = sprintf(
                'Sincronizacion: provincias +%d / %d actualizadas; municipios +%d / %d; entidades +%d, codigos %d, otros %d.',
                $pr['created'] ?? 0,
                $pr['updated'] ?? 0,
                $mu['created'] ?? 0,
                $mu['updated'] ?? 0,
                $en['created'] ?? 0,
                $en['updated_codes'] ?? 0,
                $en['updated_data'] ?? 0
            );
        } else {
            $msg = "Sincronizacion completada: {$result['created']} creadas, {$result['updated_codes']} codigos actualizados";
            if (($result['updated_data'] ?? 0) > 0) {
                $msg .= ", {$result['updated_data']} registros ajustados";
            }
            $msg .= '.';
        }

        return back()->with('success', $msg);
    }
}
