<?php

namespace App\Http\Controllers;

use App\Models\ExternalCiSetting;
use App\Models\LdapSetting;
use App\Models\Municipio;
use App\Models\Trabajador;
use App\Services\ExternalCiService;
use App\Services\LdapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrabajadorController extends Controller
{
    public function index(): Response
    {
        $ldap = LdapSetting::current();
        $ciCfg = ExternalCiSetting::current();

        return Inertia::render('Nomencladores/Trabajador/Index', [
            'trabajadores' => Trabajador::withTrashed()
                ->with('municipio:id,name')
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('nombre')
                ->get(),
            'municipios' => Municipio::orderBy('name')->get(['id', 'name']),
            'ldapEnabled' => (bool) ($ldap->enabled && $ldap->host),
            'externalCiEnabled' => (bool) $ciCfg->enabled,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $origen = $request->input('origen', 'manual');

        $ciRules = $origen === 'active_directory'
            ? ['nullable', 'string', 'size:11', 'regex:/^[0-9]{11}$/', 'unique:trabajadores,ci']
            : ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/', 'unique:trabajadores,ci'];

        $telefonoRules = $origen === 'active_directory'
            ? ['nullable', 'string', 'max:15', 'regex:/^[0-9+\-\s()]+$/']
            : ['required', 'string', 'max:15', 'regex:/^[0-9+\-\s()]+$/'];

        $municipioRules = $origen === 'active_directory'
            ? 'nullable|exists:municipios,id'
            : 'required|exists:municipios,id';

        $data = $request->validate([
            'nombre'         => 'required|string|max:200',
            'ci'             => $ciRules,
            'telefono'       => $telefonoRules,
            'direccion'      => 'nullable|string|max:255',
            'municipio_id'   => $municipioRules,
            'estado'         => 'boolean',
            'origen'         => 'nullable|in:manual,ci_externo,active_directory',
            'samaccountname' => 'nullable|string|max:100|unique:trabajadores,samaccountname',
            'cargo'          => 'nullable|string|max:200',
            'email'          => 'nullable|string|max:255',
        ]);

        $data['origen'] = $origen;

        $dup = Trabajador::findExistingMatch(
            $data['ci'] ?? null,
            $data['samaccountname'] ?? null,
            $data['nombre']
        );
        if ($dup) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe un trabajador con el mismo CI, usuario de red o nombre equivalente (normalizado).',
            ]);
        }

        Trabajador::create($data);

        return back()->with('success', 'Trabajador creado.');
    }

    public function update(Request $request, Trabajador $trabajador): RedirectResponse
    {
        $origen = $request->input('origen', $trabajador->origen ?? 'manual');

        $ciRules = $origen === 'active_directory'
            ? ['nullable', 'string', 'size:11', 'regex:/^[0-9]{11}$/', "unique:trabajadores,ci,{$trabajador->id}"]
            : ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/', "unique:trabajadores,ci,{$trabajador->id}"];

        $telefonoRules = $origen === 'active_directory'
            ? ['nullable', 'string', 'max:15', 'regex:/^[0-9+\-\s()]+$/']
            : ['required', 'string', 'max:15', 'regex:/^[0-9+\-\s()]+$/'];

        $municipioRules = $origen === 'active_directory'
            ? 'nullable|exists:municipios,id'
            : 'required|exists:municipios,id';

        $data = $request->validate([
            'nombre'         => 'required|string|max:200',
            'ci'             => $ciRules,
            'telefono'       => $telefonoRules,
            'direccion'      => 'nullable|string|max:255',
            'municipio_id'   => $municipioRules,
            'estado'         => 'boolean',
            'origen'         => 'nullable|in:manual,ci_externo,active_directory',
            'samaccountname' => ['nullable', 'string', 'max:100', "unique:trabajadores,samaccountname,{$trabajador->id}"],
            'cargo'          => 'nullable|string|max:200',
            'email'          => 'nullable|string|max:255',
        ]);

        $data['origen'] = $origen;

        $dup = Trabajador::findExistingMatch(
            $data['ci'] ?? null,
            $data['samaccountname'] ?? null,
            $data['nombre']
        );
        if ($dup && (int) $dup->id !== (int) $trabajador->id) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe otro trabajador con el mismo CI, usuario de red o nombre equivalente (normalizado).',
            ]);
        }

        $trabajador->restore();
        $trabajador->update($data);

        return back()->with('success', 'Trabajador actualizado.');
    }

    public function destroy(Trabajador $trabajador): RedirectResponse
    {
        $trabajador->update(['estado' => false]);
        $trabajador->delete();

        return back()->with('success', 'Trabajador dado de baja (inactivo).');
    }

    public function reactivar(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Administrador'), 403);

        $trabajador = Trabajador::withTrashed()->findOrFail($id);

        if ($trabajador->trashed()) {
            $trabajador->restore();
        }

        $trabajador->update(['estado' => true]);

        return back()->with('success', 'Trabajador reactivado (restaurado en el sistema).');
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Administrador'), 403);

        $trabajador = Trabajador::withTrashed()->findOrFail($id);

        if (! $trabajador->trashed()) {
            return back()->with('error', 'Solo se puede borrar definitivamente un trabajador que ya esté en la papelera (dé de baja primero).');
        }

        $trabajador->forceDelete();

        return back()->with('success', 'Trabajador eliminado definitivamente de la base de datos.');
    }

    public function buscarPorCi(string $ci): JsonResponse
    {
        if (!preg_match('/^[0-9]{11}$/', $ci)) {
            return response()->json([
                'success' => false,
                'message' => 'El CI debe tener exactamente 11 dígitos.',
            ], 422);
        }

        return response()->json(ExternalCiService::findByCi($ci));
    }

    public function buscarPorAd(Request $request): JsonResponse
    {
        $cfg = LdapSetting::current();
        if (! $cfg->enabled || ! $cfg->host) {
            return response()->json([
                'users'   => [],
                'message' => 'LDAP no está activo en configuración.',
            ], 422);
        }

        $data = $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        if (! LdapService::registerConnectionFromDatabaseIfReady()) {
            return response()->json([
                'users'   => [],
                'message' => 'No se pudo usar el directorio LDAP. Revisa la configuración.',
            ], 503);
        }

        $users = LdapService::searchUsers($data['query']);

        // Para cada usuario AD, verificar si ya existe como trabajador
        $enriched = collect($users)->map(function ($u) {
            $existing = Trabajador::findExistingMatch(
                null,
                $u['samaccountname'] ?? null,
                $u['displayname'] ?? null,
            );

            $u['trabajador_existente'] = $existing ? [
                'id'     => $existing->id,
                'nombre' => $existing->nombre,
                'ci'     => $existing->ci,
                'origen' => $existing->origen,
            ] : null;

            return $u;
        })->all();

        return response()->json(['users' => $enriched]);
    }

    public function crearDesdeAd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'         => 'required|string|max:200',
            'samaccountname' => 'required|string|max:100',
            'cargo'          => 'nullable|string|max:200',
            'email'          => 'nullable|string|max:255',
        ]);

        // Verificar si ya existe
        $existing = Trabajador::findExistingMatch(null, $data['samaccountname'], $data['nombre']);

        if ($existing) {
            // Actualizar datos AD si están vacíos
            $updates = [];
            if (! $existing->samaccountname && $data['samaccountname']) {
                $updates['samaccountname'] = $data['samaccountname'];
            }
            if (! $existing->cargo && ($data['cargo'] ?? null)) {
                $updates['cargo'] = $data['cargo'];
            }
            if (! $existing->email && ($data['email'] ?? null)) {
                $updates['email'] = $data['email'];
            }
            if ($updates) {
                $existing->update($updates);
            }

            return response()->json([
                'success'    => true,
                'vinculado'  => true,
                'message'    => "Trabajador ya existente: {$existing->nombre} (vinculado).",
                'trabajador' => $existing->fresh(),
            ]);
        }

        $trabajador = Trabajador::create([
            'nombre'         => $data['nombre'],
            'samaccountname' => $data['samaccountname'],
            'cargo'          => $data['cargo'] ?? null,
            'email'          => $data['email'] ?? null,
            'origen'         => 'active_directory',
            'estado'         => true,
        ]);

        return response()->json([
            'success'    => true,
            'vinculado'  => false,
            'message'    => 'Trabajador creado desde Active Directory.',
            'trabajador' => $trabajador,
        ]);
    }

    public function vincularCi(Request $request, Trabajador $trabajador): JsonResponse
    {
        if ($trabajador->origen !== 'active_directory') {
            return response()->json([
                'success' => false,
                'message' => 'La vinculación con CI externo aplica a trabajadores cargados desde Active Directory.',
            ], 422);
        }

        $data = $request->validate([
            'ci' => ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/'],
        ]);
        $ci = $data['ci'];

        if (Trabajador::withTrashed()->where('ci', $ci)->where('id', '!=', $trabajador->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ese CI ya está asignado a otro trabajador.',
            ], 422);
        }

        $ext = ExternalCiService::findByCi($ci);
        if (! ($ext['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $ext['message'] ?? 'No se pudo consultar el CI en la base externa.',
            ], 422);
        }

        $payload = $ext['trabajador'];
        $updates = ['ci' => $payload['ci']];
        if (! empty($payload['direccion'])) {
            $updates['direccion'] = $payload['direccion'];
        }
        if (! empty($payload['telefono'] ?? null)) {
            $updates['telefono'] = $payload['telefono'];
        }

        $trabajador->update($updates);

        return response()->json([
            'success'    => true,
            'message'    => 'CI vinculado con la base externa.',
            'trabajador' => $trabajador->fresh(['municipio:id,name']),
        ]);
    }

    public function vincularAd(Request $request, Trabajador $trabajador): JsonResponse
    {
        if (! in_array($trabajador->origen, ['manual', 'ci_externo'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede vincular con AD los trabajadores registrados manualmente o por CI externo.',
            ], 422);
        }

        $cfg = LdapSetting::current();
        if (! $cfg->enabled || ! $cfg->host) {
            return response()->json([
                'success' => false,
                'message' => 'LDAP no está activo en configuración.',
            ], 422);
        }

        $data = $request->validate([
            'samaccountname' => ['required', 'string', 'max:100'],
            'cargo'          => 'nullable|string|max:200',
            'email'          => 'nullable|string|max:255',
        ]);

        $sam = $data['samaccountname'];
        if (Trabajador::withTrashed()->where('samaccountname', $sam)->where('id', '!=', $trabajador->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ese usuario de red ya está vinculado a otro trabajador.',
            ], 422);
        }

        if (! LdapService::registerConnectionFromDatabaseIfReady()) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar al directorio LDAP.',
            ], 503);
        }

        $trabajador->update([
            'samaccountname' => $sam,
            'cargo'          => $data['cargo'] ?? $trabajador->cargo,
            'email'          => $data['email'] ?? $trabajador->email,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Cuenta de Active Directory vinculada.',
            'trabajador' => $trabajador->fresh(['municipio:id,name']),
        ]);
    }
}
