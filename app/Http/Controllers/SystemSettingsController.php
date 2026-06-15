<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\ExternalAlmacenesSetting;
use App\Models\ExternalEntitiesPgSetting;
use App\Models\ExternalEntityDbSetting;
use App\Models\ExternalCiSetting;
use App\Models\LdapSetting;
use App\Models\SystemModule;
use App\Models\SystemSetting;
use App\Services\ExternalAlmacenesService;
use App\Services\ExternalEntitiesPgService;
use App\Services\ExternalEntityDbService;
use App\Services\ExternalCiService;
use App\Services\LdapService;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SystemSettingsController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        $layout = Branding::resolvedLayout();

        $ldap     = LdapSetting::current();
        $externalCi = ExternalCiSetting::current();
        $externalEntityDb = ExternalEntityDbSetting::current();
        $externalAlmacenes = ExternalAlmacenesSetting::current();
        $externalEntitiesPg = ExternalEntitiesPgSetting::with('tableMappings')->first();
        if ($externalEntitiesPg === null) {
            $externalEntitiesPg = ExternalEntitiesPgSetting::current();
            $externalEntitiesPg->load('tableMappings');
        }

        return Inertia::render('Configuracion/Index', [
            'appearance' => $layout,
            'logo_url'   => Branding::logoPublicUrl(),
            'modules'    => SystemModule::query()->orderBy('name')->get(),
            'ldap'       => [
                'enabled'          => $ldap->enabled,
                'host'             => $ldap->host,
                'port'             => $ldap->port,
                'base_dn'          => $ldap->base_dn,
                'bind_username'    => $ldap->bind_username,
                'has_password'     => $ldap->has_password,
                'use_ssl'          => $ldap->use_ssl,
                'use_tls'          => $ldap->use_tls,
                'timeout'          => $ldap->timeout,
                'user_search_base' => $ldap->user_search_base,
            ],
            'external_ci' => [
                'enabled'          => $externalCi->enabled,
                'odbc_dsn'         => $externalCi->odbc_dsn,
                'host'             => $externalCi->host,
                'port'             => $externalCi->port,
                'database_name'    => $externalCi->database_name,
                'username'         => $externalCi->username,
                'has_password'     => $externalCi->has_password,
                'table_name'       => $externalCi->table_name,
                'ci_column'        => $externalCi->ci_column,
                'nombre_column'    => $externalCi->nombre_column,
                'apellido1_column' => $externalCi->apellido1_column,
                'apellido2_column' => $externalCi->apellido2_column,
                'telefono_column'  => $externalCi->telefono_column,
                'direccion_columns' => $externalCi->direccion_columns ?? [],
                'timeout'          => $externalCi->timeout,
            ],
            'external_entities_pg' => [
                'driver'               => $externalEntitiesPg->driver ?? 'pgsql',
                'enabled'              => $externalEntitiesPg->enabled,
                'host'                 => $externalEntitiesPg->host,
                'port'                 => $externalEntitiesPg->port,
                'database_name'        => $externalEntitiesPg->database_name,
                'schema_name'          => $externalEntitiesPg->schema_name,
                'username'             => $externalEntitiesPg->username,
                'has_password'         => $externalEntitiesPg->has_password,
                'table_name'           => $externalEntitiesPg->table_name,
                'name_column'          => $externalEntitiesPg->name_column,
                'code_column'          => $externalEntitiesPg->code_column,
                'municipio_code_column'=> $externalEntitiesPg->municipio_code_column,
                'provincia_column'     => $externalEntitiesPg->provincia_column,
                'timeout'              => $externalEntitiesPg->timeout,
                'last_synced'          => $externalEntitiesPg->last_synced_at?->format('d/m/Y H:i'),
                'last_sync_summary'    => $externalEntitiesPg->last_sync_summary,
                'table_mappings'       => $externalEntitiesPg->tableMappings->map(fn($m) => [
                    'id' => $m->id,
                    'target' => $m->target,
                    'schema_name' => $m->schema_name,
                    'table_name' => $m->table_name,
                    'name_column' => $m->name_column,
                    'code_column' => $m->code_column,
                    'municipio_code_column' => $m->municipio_code_column,
                    'provincia_code_column' => $m->provincia_code_column,
                    'sigla_2_column' => $m->sigla_2_column,
                    'sigla_3_column' => $m->sigla_3_column,
                ])->values()->all(),
            ],
            'external_entity_db' => [
                'enabled'         => $externalEntityDb->enabled,
                'driver'          => $externalEntityDb->driver,
                'host'            => $externalEntityDb->host,
                'port'            => $externalEntityDb->port,
                'username'        => $externalEntityDb->username,
                'has_password'    => $externalEntityDb->has_password,
                'db_prefix'        => $externalEntityDb->db_prefix,
                'code_padding'     => $externalEntityDb->code_padding,
                'table_name'       => $externalEntityDb->table_name,
                'inventory_lookup_column' => $externalEntityDb->inventory_lookup_column,
                'areas_table'      => $externalEntityDb->areas_table,
                'area_code_column' => $externalEntityDb->area_code_column,
                'area_name_column' => $externalEntityDb->area_name_column,
                'area_column'      => $externalEntityDb->area_column,
                'grupo_column'    => $externalEntityDb->grupo_column,
                'subgrupo_column' => $externalEntityDb->subgrupo_column,
                'grupo_value'     => $externalEntityDb->grupo_value,
                'subgrupo_value'  => $externalEntityDb->subgrupo_value,
                'timeout'         => $externalEntityDb->timeout,
                'last_synced'     => $externalEntityDb->last_synced_at?->format('d/m/Y H:i'),
                'last_sync_summary' => $externalEntityDb->last_sync_summary,
            ],
            'entities_for_sync' => Entity::active()->orderBy('name')->get(['id', 'name', 'code']),
            'external_almacenes' => [
                'enabled'              => $externalAlmacenes->enabled,
                'host'                 => $externalAlmacenes->host,
                'port'                 => $externalAlmacenes->port,
                'username'             => $externalAlmacenes->username,
                'has_password'         => $externalAlmacenes->has_password,
                'database_name'        => $externalAlmacenes->database_name,
                'table_name'           => $externalAlmacenes->table_name,
                'schema_name'          => $externalAlmacenes->schema_name,
                'id_unidad_column'     => $externalAlmacenes->id_unidad_column,
                'almacen_column'       => $externalAlmacenes->almacen_column,
                'id_piso_column'       => $externalAlmacenes->id_piso_column,
                'id_almacen_pk_column' => $externalAlmacenes->id_almacen_pk_column,
                'import_solo_abierto'  => $externalAlmacenes->import_solo_abierto,
                'import_tipos'         => $externalAlmacenes->import_tipos ?? [],
                'sync_creates_areas'   => $externalAlmacenes->sync_creates_areas,
                'timeout'              => $externalAlmacenes->timeout,
                'last_synced'          => $externalAlmacenes->last_synced_at?->format('d/m/Y H:i'),
                'last_sync_summary'    => $externalAlmacenes->last_sync_summary,
                'tipo_flags'           => ExternalAlmacenesSetting::TIPO_FLAGS,
            ],
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        // Textos de encabezado/pie en PDF y Excel: misma regla que ver Configuracion.
        abort_unless($request->user()->can('configuracion.appearance'), 403);

        $data = $request->validate([
            'organization_name' => 'nullable|string|max:255',
            'system_name' => 'nullable|string|max:255',
            'header_title' => 'nullable|string|max:255',
            'footer_left' => 'nullable|string|max:255',
            'footer_right' => 'nullable|string|max:255',
        ]);

        $row = SystemSetting::query()->firstOrNew([]);
        $row->fill($data);
        $row->save();
        Branding::forgetCache();

        return back()->with('success', 'Textos de documentos y pie de pagina actualizados.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('configuracion.logo'), 403);

        $request->validate([
            'logo' => 'required|file|mimes:jpeg,jpg,png,gif,webp,svg|max:4096',
        ]);

        $path = $request->file('logo')->store('system', 'public');

        $row = SystemSetting::query()->firstOrNew([]);
        if ($row->logo_path) {
            Storage::disk('public')->delete($row->logo_path);
        }
        $row->logo_path = $path;
        $row->save();
        Branding::forgetCache();

        return back()->with('success', 'Logo actualizado correctamente.');
    }

    public function deleteLogo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('configuracion.logo.delete'), 403);

        $row = SystemSetting::query()->first();
        if ($row?->logo_path) {
            Storage::disk('public')->delete($row->logo_path);
            $row->logo_path = null;
            $row->save();
        }
        Branding::forgetCache();

        return back()->with('success', 'Logo eliminado.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LDAP / Active Directory
    // ──────────────────────────────────────────────────────────────────────────

    public function updateLdap(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('configuracion.ldap'), 403);

        $data = $request->validate([
            'enabled'          => 'boolean',
            'host'             => 'nullable|string|max:255',
            'port'             => 'nullable|integer|min:1|max:65535',
            'base_dn'          => 'nullable|string|max:500',
            'bind_username'    => 'nullable|string|max:500',
            'bind_password'    => 'nullable|string|max:500',
            'use_ssl'          => 'boolean',
            'use_tls'          => 'boolean',
            'timeout'          => 'nullable|integer|min:1|max:60',
            'user_search_base' => 'nullable|string|max:500',
        ]);

        $ldap = LdapSetting::current();
        $ldap->fill(array_filter($data, fn ($k) => $k !== 'bind_password', ARRAY_FILTER_USE_KEY));

        // Contraseña solo se actualiza si se envía un valor no vacío
        if (!empty($data['bind_password'])) {
            $ldap->bind_password = $data['bind_password'];
        }

        $ldap->save();

        // Recargar conexión en el Container si se habilitó
        if ($ldap->enabled) {
            try { LdapService::boot(); } catch (\Throwable) {}
        }

        return back()->with('success', 'Configuración del Directorio Activo actualizada.');
    }

    public function testLdap(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.ldap.test'), 403);

        $data = $request->validate([
            'host'          => 'required|string|max:255',
            'port'          => 'nullable|integer',
            'base_dn'       => 'nullable|string|max:500',
            'bind_username' => 'nullable|string|max:500',
            'bind_password' => 'nullable|string|max:500',
            'use_ssl'       => 'nullable|boolean',
            'use_tls'       => 'nullable|boolean',
            'timeout'       => 'nullable|integer',
        ]);

        // Si no viene contraseña en el request, usar la guardada en BD
        if (empty($data['bind_password'])) {
            $data['bind_password'] = LdapSetting::current()->bind_password;
        }

        // Construir un LdapSetting temporal (no persistido)
        $tmpCfg                  = new LdapSetting();
        $tmpCfg->host            = $data['host'];
        $tmpCfg->port            = $data['port'] ?? 389;
        $tmpCfg->base_dn         = $data['base_dn'] ?? '';
        $tmpCfg->bind_username   = $data['bind_username'] ?? '';
        $tmpCfg->use_ssl         = (bool) ($data['use_ssl'] ?? false);
        $tmpCfg->use_tls         = (bool) ($data['use_tls'] ?? false);
        $tmpCfg->timeout         = $data['timeout'] ?? 5;
        $rawBind = $data['bind_password'] ? encrypt($data['bind_password']) : null;
        $tmpCfg->setRawAttributes(array_merge($tmpCfg->getAttributes(), [
            'bind_password' => $rawBind,
        ]));

        return response()->json(LdapService::testConnection($tmpCfg));
    }

    public function searchLdap(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.ldap.search'), 403);

        $data = $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        $users = LdapService::searchUsers($data['query']);

        $payload = ['users' => $users];
        if (config('app.debug')) {
            $payload['ldap_debug'] = LdapService::getLastSearchDiagnostics();
        }

        return response()->json($payload);
    }

    public function updateExternalCi(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('configuracion.external-ci'), 403);

        $hasOdbc = !empty($request->input('odbc_dsn'));

        $data = $request->validate([
            'enabled'          => 'required|boolean',
            'odbc_dsn'         => 'nullable|string|max:255',
            'host'             => $hasOdbc ? 'nullable|string|max:255' : 'required|string|max:255',
            'port'             => $hasOdbc ? 'nullable|integer|min:1|max:65535' : 'required|integer|min:1|max:65535',
            'database_name'    => $hasOdbc ? 'nullable|string|max:255' : 'required|string|max:255',
            'username'         => 'nullable|string|max:255',
            'password'         => 'nullable|string|max:500',
            'table_name'       => 'required|string|max:255',
            'ci_column'        => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'nombre_column'    => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'apellido1_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'apellido2_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'telefono_column'  => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'direccion_columns' => 'nullable|array|min:1',
            'direccion_columns.*' => ['string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'timeout'          => 'required|integer|min:1|max:60',
        ]);

        $cfg = ExternalCiSetting::current();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $cfg->fill($data);

        if (!empty($request->password)) {
            $cfg->password = $request->password;
        }
        $cfg->save();

        return back()->with('success', 'Configuración SQL Server (consulta CI) guardada.');
    }

    public function testExternalCi(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-ci.test'), 403);

        $hasOdbc = !empty($request->input('odbc_dsn'));

        $data = $request->validate([
            'odbc_dsn'         => 'nullable|string|max:255',
            'host'             => $hasOdbc ? 'nullable|string|max:255' : 'required|string|max:255',
            'port'             => $hasOdbc ? 'nullable|integer|min:1|max:65535' : 'required|integer|min:1|max:65535',
            'database_name'    => $hasOdbc ? 'nullable|string|max:255' : 'required|string|max:255',
            'username'         => 'nullable|string|max:255',
            'password'         => 'nullable|string|max:500',
            'table_name'       => 'required|string|max:255',
            'ci_column'        => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'nombre_column'    => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'apellido1_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'apellido2_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'telefono_column'  => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'direccion_columns' => 'nullable|array|min:1',
            'direccion_columns.*' => ['string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'timeout'          => 'required|integer|min:1|max:60',
        ]);

        $tmp = new ExternalCiSetting();
        $tmp->odbc_dsn = $data['odbc_dsn'] ?? null;
        $tmp->host = $data['host'] ?? null;
        $tmp->port = $data['port'] ?? null;
        $tmp->database_name = $data['database_name'] ?? null;
        $tmp->username = $data['username'] ?? null;
        $tmp->table_name = $data['table_name'];
        $tmp->ci_column = $data['ci_column'];
        $tmp->nombre_column = $data['nombre_column'];
        $tmp->apellido1_column = $data['apellido1_column'];
        $tmp->apellido2_column = $data['apellido2_column'];
        $tmp->telefono_column = $data['telefono_column'];
        $tmp->direccion_columns = $data['direccion_columns'] ?? [];
        $tmp->timeout = $data['timeout'];

        if (!empty($data['password'])) {
            $tmp->password = $data['password'];
        } else {
            $saved = ExternalCiSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        }

        return response()->json(ExternalCiService::testConnection($tmp));
    }

    public function columnsExternalCi(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-ci.test'), 403);

        $hasOdbc = !empty($request->input('odbc_dsn'));

        $data = $request->validate([
            'odbc_dsn'      => 'nullable|string|max:255',
            'host'          => $hasOdbc ? 'nullable|string|max:255' : 'required|string|max:255',
            'port'          => $hasOdbc ? 'nullable|integer|min:1|max:65535' : 'required|integer|min:1|max:65535',
            'database_name' => $hasOdbc ? 'nullable|string|max:255' : 'required|string|max:255',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string|max:500',
            'table_name'    => 'required|string|max:255',
            'timeout'       => 'required|integer|min:1|max:60',
        ]);

        $tmp = new ExternalCiSetting();
        $tmp->odbc_dsn = $data['odbc_dsn'] ?? null;
        $tmp->host = $data['host'] ?? null;
        $tmp->port = $data['port'] ?? null;
        $tmp->database_name = $data['database_name'] ?? null;
        $tmp->username = $data['username'] ?? null;
        $tmp->table_name = $data['table_name'];
        $tmp->timeout = $data['timeout'];

        if (!empty($data['password'])) {
            $tmp->password = $data['password'];
        } else {
            $saved = ExternalCiSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        }

        try {
            $columns = ExternalCiService::getTableColumns($tmp);
            return response()->json(['success' => true, 'columns' => $columns]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateExternalEntitiesPg(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('configuracion.external-entities-pg'), 403);

        $hasMappings = is_array($request->input('table_mappings')) && count($request->input('table_mappings')) > 0;

        $baseRules = [
            'driver'        => 'required|in:pgsql,mysql,mariadb,sqlsrv',
            'enabled'       => 'required|boolean',
            'host'          => 'required|string|max:255',
            'port'          => 'required|integer|min:1|max:65535',
            'database_name' => 'required|string|max:255',
            'schema_name'   => 'nullable|string|max:128',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string|max:500',
            'timeout'       => 'required|integer|min:1|max:60',
        ];

        $rules = $hasMappings
            ? array_merge($baseRules, [
                'table_mappings' => 'required|array|min:1',
                'table_mappings.*.target' => 'required|in:entity,provincia,municipio',
                'table_mappings.*.schema_name' => 'nullable|string|max:128',
                'table_mappings.*.table_name' => 'required|string|max:255',
                'table_mappings.*.name_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'table_mappings.*.code_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'table_mappings.*.municipio_code_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'table_mappings.*.provincia_code_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'table_mappings.*.sigla_2_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'table_mappings.*.sigla_3_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'table_name' => 'nullable|string|max:255',
                'name_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'code_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'municipio_code_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'provincia_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            ])
            : array_merge($baseRules, [
                'table_name'            => 'required|string|max:255',
                'name_column'           => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'code_column'           => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'municipio_code_column' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'provincia_column'      => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
                'table_mappings'        => 'nullable|array',
            ]);

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function (\Illuminate\Validation\Validator $v) use ($request): void {
            foreach ($request->input('table_mappings', []) as $i => $row) {
                if (($row['target'] ?? '') === 'municipio' && trim((string) ($row['provincia_code_column'] ?? '')) === '') {
                    $v->errors()->add(
                        "table_mappings.$i.provincia_code_column",
                        'Para destino Municipio indique la columna del código de provincia en PostgreSQL.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $cfg = ExternalEntitiesPgSetting::current();
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $cfg->driver        = $data['driver'];
        $cfg->enabled       = $data['enabled'];
        $cfg->host          = $data['host'];
        $cfg->port          = $data['port'];
        $cfg->database_name = $data['database_name'];
        $cfg->schema_name   = $data['schema_name'] ?? null;
        $cfg->username      = $data['username'] ?? null;
        $cfg->timeout       = $data['timeout'];

        if ($hasMappings) {
            $cfg->table_name = $data['table_name'] ?? $cfg->table_name ?? 'entities';
            $cfg->name_column = $data['name_column'] ?? $cfg->name_column ?? 'name';
            $cfg->code_column = $data['code_column'] ?? $cfg->code_column ?? 'code';
            $cfg->municipio_code_column = $data['municipio_code_column'] ?? $cfg->municipio_code_column;
            $cfg->provincia_column = $data['provincia_column'] ?? null;
        } else {
            $cfg->table_name = $data['table_name'];
            $cfg->name_column = $data['name_column'];
            $cfg->code_column = $data['code_column'];
            $cfg->municipio_code_column = $data['municipio_code_column'] ?? null;
            $cfg->provincia_column = $data['provincia_column'] ?? null;
        }

        if (! empty($request->password)) {
            $cfg->password = $request->password;
        }

        $cfg->save();

        $cfg->tableMappings()->delete();

        if ($hasMappings) {
            foreach ($data['table_mappings'] as $i => $row) {
                $cfg->tableMappings()->create([
                    'sort_order' => $i,
                    'enabled' => true,
                    'target' => $row['target'],
                    'schema_name' => ! empty($row['schema_name']) ? $row['schema_name'] : null,
                    'table_name' => $row['table_name'],
                    'name_column' => $row['name_column'],
                    'code_column' => $row['code_column'],
                    'municipio_code_column' => ! empty($row['municipio_code_column']) ? $row['municipio_code_column'] : null,
                    'provincia_code_column' => ! empty($row['provincia_code_column']) ? $row['provincia_code_column'] : null,
                    'sigla_2_column' => ! empty($row['sigla_2_column']) ? $row['sigla_2_column'] : null,
                    'sigla_3_column' => ! empty($row['sigla_3_column']) ? $row['sigla_3_column'] : null,
                ]);
            }
        }

        return back()->with('success', 'Configuracion PostgreSQL externa (Entidades) guardada.');
    }

    public function testExternalEntitiesPg(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-entities-pg.test'), 403);

        $data = $request->validate([
            'driver'        => 'required|in:pgsql,mysql,mariadb,sqlsrv',
            'host'          => 'required|string|max:255',
            'port'          => 'required|integer|min:1|max:65535',
            'database_name' => 'required|string|max:255',
            'schema_name'   => 'nullable|string|max:128',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string|max:500',
            'timeout'       => 'required|integer|min:1|max:60',
        ]);

        $tmp                = new ExternalEntitiesPgSetting();
        $tmp->driver        = $data['driver'];
        $tmp->host          = $data['host'];
        $tmp->port          = $data['port'];
        $tmp->database_name = $data['database_name'];
        $tmp->schema_name   = $data['schema_name'] ?? null;
        $tmp->username      = $data['username'] ?? null;
        $tmp->timeout       = $data['timeout'];

        if (! empty($data['password'])) {
            $tmp->password = $data['password'];
        } else {
            $saved = ExternalEntitiesPgSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        }

        return response()->json(ExternalEntitiesPgService::testConnection($tmp));
    }

    public function tablesExternalEntitiesPg(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-entities-pg.test'), 403);

        $data = $request->validate([
            'driver'        => 'required|in:pgsql,mysql,mariadb,sqlsrv',
            'host'          => 'required|string|max:255',
            'port'          => 'required|integer|min:1|max:65535',
            'database_name' => 'required|string|max:255',
            'schema_name'   => 'nullable|string|max:128',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string|max:500',
            'timeout'       => 'required|integer|min:1|max:60',
        ]);

        $tmp                = new ExternalEntitiesPgSetting();
        $tmp->driver        = $data['driver'];
        $tmp->host          = $data['host'];
        $tmp->port          = $data['port'];
        $tmp->database_name = $data['database_name'];
        $tmp->schema_name   = $data['schema_name'] ?? null;
        $tmp->username      = $data['username'] ?? null;
        $tmp->timeout       = $data['timeout'];

        if (! empty($data['password'])) {
            $tmp->password = $data['password'];
        } else {
            $saved = ExternalEntitiesPgSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        }

        try {
            $tables = ExternalEntitiesPgService::listTables($tmp, $data['schema_name'] ?? null);

            return response()->json(['success' => true, 'tables' => $tables]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => mb_scrub($e->getMessage(), 'UTF-8')], 422);
        }
    }

    public function columnsExternalEntitiesPg(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-entities-pg.test'), 403);

        $data = $request->validate([
            'driver'        => 'required|in:pgsql,mysql,mariadb,sqlsrv',
            'host'          => 'required|string|max:255',
            'port'          => 'required|integer|min:1|max:65535',
            'database_name' => 'required|string|max:255',
            'schema_name'   => 'nullable|string|max:128',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string|max:500',
            'timeout'       => 'required|integer|min:1|max:60',
            'table_name'    => 'required|string|max:255',
        ]);

        $tmp                = new ExternalEntitiesPgSetting();
        $tmp->driver        = $data['driver'];
        $tmp->host          = $data['host'];
        $tmp->port          = $data['port'];
        $tmp->database_name = $data['database_name'];
        $tmp->schema_name   = $data['schema_name'] ?? null;
        $tmp->username      = $data['username'] ?? null;
        $tmp->timeout       = $data['timeout'];

        if (! empty($data['password'])) {
            $tmp->password = $data['password'];
        } else {
            $saved = ExternalEntitiesPgSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        }

        try {
            $columns = ExternalEntitiesPgService::listColumns($tmp, $data['table_name'], $data['schema_name'] ?? null);

            return response()->json(['success' => true, 'columns' => $columns]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => mb_scrub($e->getMessage(), 'UTF-8')], 422);
        }
    }

    public function testAndSyncExternalEntitiesPg(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-entities-pg.test'), 403);

        $cfg = ExternalEntitiesPgSetting::current();
        $test = ExternalEntitiesPgService::testConnection($cfg);
        if (!$test['success']) {
            $cfg->last_sync_summary = [
                'ok' => false,
                'error' => $test['message'] ?? 'Error de conexion.',
                'phase' => 'connection',
                'at' => now()->toIso8601String(),
            ];
            $cfg->save();

            return response()->json($test, 422);
        }

        $sync = ExternalEntitiesPgService::syncEntities($cfg);
        if (! $sync['success']) {
            return response()->json($sync, 422);
        }

        if (($sync['mode'] ?? '') === 'mappings') {
            $pr = $sync['provincias'] ?? ['created' => 0, 'updated' => 0];
            $mu = $sync['municipios'] ?? ['created' => 0, 'updated' => 0];
            $en = $sync['entities'] ?? ['created' => 0, 'updated_codes' => 0, 'updated_data' => 0];
            $message = sprintf(
                'Sincronizacion: provincias +%d / actualizadas %d; municipios +%d / %d; entidades +%d, codigos %d, otros ajustes %d.',
                $pr['created'] ?? 0,
                $pr['updated'] ?? 0,
                $mu['created'] ?? 0,
                $mu['updated'] ?? 0,
                $en['created'] ?? 0,
                $en['updated_codes'] ?? 0,
                $en['updated_data'] ?? 0
            );
        } else {
            $message = "Sincronizacion completada: {$sync['created']} creadas, {$sync['updated_codes']} codigos actualizados, {$sync['updated_data']} registros ajustados.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'mode' => $sync['mode'] ?? 'legacy',
            'created' => $sync['created'] ?? 0,
            'updated_codes' => $sync['updated_codes'] ?? 0,
            'updated_data' => $sync['updated_data'] ?? 0,
            'provincias' => $sync['provincias'] ?? null,
            'municipios' => $sync['municipios'] ?? null,
            'entities' => $sync['entities'] ?? null,
        ]);
    }

    /**
     * Vista previa (dry-run): muestra qué se crearía/actualizaría sin persistir nada.
     */
    public function previewExternalEntitiesPg(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-entities-pg.test'), 403);

        $cfg  = ExternalEntitiesPgSetting::with('tableMappings')->first();
        if ($cfg === null) {
            $cfg = ExternalEntitiesPgSetting::current();
        }

        // Si no hay mapeos de tablas y la config es la de fábrica, el usuario
        // no ha guardado su configuración todavía.
        $hasMappings = $cfg->tableMappings->where('enabled', true)->isNotEmpty();
        if (! $hasMappings && ($cfg->table_name === 'entities' || ! $cfg->table_name)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay mapeos de tablas configurados. '
                    .'Configura los origenes en modo avanzado y pulsa "Guardar configuracion" antes de ejecutar la vista previa.',
            ], 422);
        }

        // Filtro opcional: solo los orígenes seleccionados
        $onlyMappingIds = null;
        if ($request->has('mapping_ids') && is_array($request->input('mapping_ids'))) {
            $onlyMappingIds = array_map('intval', $request->input('mapping_ids'));
        }

        $test = ExternalEntitiesPgService::testConnection($cfg);

        if (! $test['success']) {
            return response()->json([
                'success' => false,
                'message' => $test['message'] ?? 'Error de conexion.',
            ], 422);
        }

        $preview = ExternalEntitiesPgService::syncEntities($cfg, dryRun: true, onlyMappingIds: $onlyMappingIds);

        if (! $preview['success']) {
            return response()->json($preview, 422);
        }

        if (($preview['mode'] ?? '') === 'mappings') {
            $pr = $preview['provincias'] ?? ['created' => 0, 'updated' => 0];
            $mu = $preview['municipios'] ?? ['created' => 0, 'updated' => 0];
            $en = $preview['entities']   ?? ['created' => 0, 'updated_codes' => 0, 'updated_data' => 0];
            $message = sprintf(
                'Vista previa: provincias +%d / actualizar %d; municipios +%d / %d; entidades +%d, codigos %d, otros %d.',
                $pr['created'] ?? 0, $pr['updated'] ?? 0,
                $mu['created'] ?? 0, $mu['updated'] ?? 0,
                $en['created'] ?? 0, $en['updated_codes'] ?? 0, $en['updated_data'] ?? 0
            );
        } else {
            $message = "Vista previa: {$preview['created']} nuevas, {$preview['updated_codes']} codigos, {$preview['updated_data']} ajustes.";
        }

        return response()->json(array_merge($preview, ['message' => $message]));
    }

    /**
     * Aplica exactamente los registros que el usuario aprobó en la vista previa.
     */
    public function applySelectedChanges(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.external-entities-pg.test'), 403);

        $data = $request->validate([
            'changes'                           => 'required|array',
            'changes.provincias'                => 'nullable|array',
            'changes.provincias.*.action'       => 'required|in:create,update',
            'changes.provincias.*.local_id'     => 'nullable|integer',
            'changes.provincias.*.name'         => 'required|string|max:255',
            'changes.provincias.*.code'         => 'required|string|max:50',
            'changes.municipios'                => 'nullable|array',
            'changes.municipios.*.action'       => 'required|in:create,update',
            'changes.municipios.*.local_id'     => 'nullable|integer',
            'changes.municipios.*.name'         => 'required|string|max:255',
            'changes.municipios.*.code'         => 'required|string|max:50',
            'changes.municipios.*.provincia_id' => 'nullable|integer',
            'changes.entities'                  => 'nullable|array',
            'changes.entities.*.action'         => 'required|in:create,update',
            'changes.entities.*.local_id'       => 'nullable|integer',
            'changes.entities.*.name'           => 'required|string|max:255',
            'changes.entities.*.code'           => 'required|string|max:50',
        ]);

        try {
            $result = ExternalEntitiesPgService::applySelectedChanges($data['changes']);

            $ap = $result['applied'];
            $message = "Cambios aplicados: {$ap['provincias']} provincia(s), {$ap['municipios']} municipio(s), {$ap['entities']} entidad(es).";

            return response()->json(array_merge($result, ['message' => $message]));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aplicar cambios: '.mb_scrub($e->getMessage(), 'UTF-8'),
            ], 422);
        }
    }

    public function updateModule(Request $request, SystemModule $modulo): RedirectResponse
    {
        // Misma regla que ver la pantalla: quien puede ver Configuracion puede activar/desactivar modulos.
        abort_unless($request->user()->can('modulos.update'), 403);

        $data = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $modulo->update($data);

        return back()->with('success', 'Configuracion del modulo actualizada.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BD Entidades (Departamentos)
    // ──────────────────────────────────────────────────────────────────────────

    public function updateExternalEntityDb(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        $data = $request->validate([
            'enabled'         => 'required|boolean',
            'driver'          => 'required|in:mysql,mariadb,pgsql,sqlsrv',
            'host'            => 'required|string|max:255',
            'port'            => 'required|integer|min:1|max:65535',
            'username'        => 'nullable|string|max:255',
            'password'        => 'nullable|string|max:500',
            'db_prefix'        => 'required|string|max:20',
            'code_padding'     => 'required|integer|min:0|max:20',
            'table_name'       => 'required|string|max:255',
            'inventory_lookup_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'areas_table'      => 'required|string|max:255',
            'area_code_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'area_name_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'area_column'      => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'grupo_column'    => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'subgrupo_column' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'grupo_value'     => 'required|integer|min:0',
            'subgrupo_value'  => 'required|integer|min:0',
            'timeout'         => 'required|integer|min:1|max:60',
        ]);

        $cfg = ExternalEntityDbSetting::current();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $cfg->fill($data);
        if (isset($data['password'])) {
            $cfg->password = $data['password'];
        }
        $cfg->save();

        return back()->with('success', 'Configuracion de BD de entidades guardada.');
    }

    public function testExternalEntityDb(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        $data = $request->validate([
            'driver'      => 'required|in:mysql,mariadb,pgsql,sqlsrv',
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer|min:1|max:65535',
            'username'    => 'nullable|string|max:255',
            'password'    => 'nullable|string|max:500',
            'db_prefix'    => 'required|string|max:20',
            'code_padding' => 'required|integer|min:0|max:20',
            'timeout'      => 'required|integer|min:1|max:60',
            'entity_code'  => 'required|string|max:50',
        ]);

        $tmp = new ExternalEntityDbSetting($data);
        $entityCode = $data['entity_code'];

        if (empty($data['password'])) {
            $saved = ExternalEntityDbSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        } else {
            $tmp->password = $data['password'];
        }

        $result = ExternalEntityDbService::testConnection($tmp, $entityCode);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function previewExternalEntityDb(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        $data = $request->validate([
            'entity_ids'   => 'nullable|array',
            'entity_ids.*' => 'integer|exists:entidades,id',
        ]);

        $cfg    = ExternalEntityDbSetting::current();
        $result = ExternalEntityDbService::previewDepartments($cfg, $data['entity_ids'] ?? null);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function applySelectedDepartmentChanges(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        // JSON puede enviar codigo_area / entity_code como número; la regla string falla si no se normaliza.
        $raw = $request->input('changes', []);
        $normalized = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (array_key_exists('codigo_area', $row) && $row['codigo_area'] !== null) {
                $row['codigo_area'] = (string) $row['codigo_area'];
            }
            if (array_key_exists('entity_code', $row) && $row['entity_code'] !== null) {
                $row['entity_code'] = (string) $row['entity_code'];
            }
            if (array_key_exists('name', $row) && $row['name'] !== null) {
                $row['name'] = (string) $row['name'];
            }
            $normalized[] = $row;
        }
        $request->merge(['changes' => $normalized]);

        $data = $request->validate([
            'changes'                  => 'required|array',
            'changes.*.action'         => 'required|in:create,reactivate,update_name',
            'changes.*.entity_id'      => 'required|integer|exists:entidades,id',
            'changes.*.entity_code'    => 'required|string|max:20',
            'changes.*.codigo_area'    => 'required|string|max:50',
            'changes.*.name'           => 'required|string|max:255',
            'changes.*.local_id'       => 'nullable|integer',
        ]);

        $result = ExternalEntityDbService::applySelectedDepartmentChanges($data['changes']);

        return response()->json($result);
    }

    public function browseExternalEntityDatabases(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        $data = $request->validate([
            'driver'       => 'required|in:mysql,mariadb,pgsql,sqlsrv',
            'host'         => 'required|string|max:255',
            'port'         => 'required|integer|min:1|max:65535',
            'username'     => 'nullable|string|max:255',
            'password'     => 'nullable|string|max:500',
            'db_prefix'    => 'required|string|max:20',
            'code_padding' => 'required|integer|min:0|max:20',
            'timeout'      => 'required|integer|min:1|max:60',
        ]);

        $tmp = new ExternalEntityDbSetting($data);

        if (empty($data['password'])) {
            $saved = ExternalEntityDbSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        } else {
            $tmp->password = $data['password'];
        }

        $result = ExternalEntityDbService::listDatabases($tmp);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function browseExternalEntityTables(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        $data = $request->validate([
            'driver'    => 'required|in:mysql,mariadb,pgsql,sqlsrv',
            'host'      => 'required|string|max:255',
            'port'      => 'required|integer|min:1|max:65535',
            'username'  => 'nullable|string|max:255',
            'password'  => 'nullable|string|max:500',
            'db_prefix' => 'required|string|max:20',
            'code_padding' => 'required|integer|min:0|max:20',
            'timeout'   => 'required|integer|min:1|max:60',
            'db_name'   => 'required|string|max:255',
        ]);

        $tmp = new ExternalEntityDbSetting($data);

        if (empty($data['password'])) {
            $saved = ExternalEntityDbSetting::current();
            $tmp->setRawAttributes(array_merge($tmp->getAttributes(), [
                'password' => $saved->getRawOriginal('password'),
            ]));
        } else {
            $tmp->password = $data['password'];
        }

        $result = ExternalEntityDbService::listTables($tmp, $data['db_name']);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function syncExternalEntityDb(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.index'), 403);

        $data = $request->validate([
            'entity_ids'   => 'nullable|array',
            'entity_ids.*' => 'integer|exists:entidades,id',
        ]);

        $cfg    = ExternalEntityDbSetting::current();
        $result = ExternalEntityDbService::syncDepartments($cfg, $data['entity_ids'] ?? null);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BD Almacenes Externos
    // ──────────────────────────────────────────────────────────────────────────

    public function updateExternalAlmacenes(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('configuracion.almacenes-externos'), 403);

        $data = $request->validate([
            'enabled'              => 'boolean',
            'host'                 => 'nullable|string|max:255',
            'port'                 => 'nullable|integer|min:1|max:65535',
            'username'             => 'nullable|string|max:255',
            'password'             => 'nullable|string|max:1000',
            'database_name'        => 'nullable|string|max:255',
            'table_name'           => 'nullable|string|max:100',
            'schema_name'          => 'nullable|string|max:50',
            'id_unidad_column'     => 'nullable|string|max:100',
            'almacen_column'       => 'nullable|string|max:100',
            'id_piso_column'       => 'nullable|string|max:100',
            'id_almacen_pk_column' => 'nullable|string|max:100',
            'import_solo_abierto'  => 'boolean',
            'import_tipos'         => 'nullable|array',
            'import_tipos.*'       => 'string|in:' . implode(',', ExternalAlmacenesSetting::TIPO_FLAGS),
            'sync_creates_areas'   => 'boolean',
            'timeout'              => 'nullable|integer|min:1|max:60',
        ]);

        $cfg = ExternalAlmacenesSetting::current();

        // Solo actualizar password si se envió uno nuevo
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $cfg->update($data);

        return back()->with('success', 'Configuración de BD Almacenes guardada.');
    }

    public function testExternalAlmacenes(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.almacenes-externos.test'), 403);

        // Construir cfg temporal con los datos del request para probar antes de guardar
        $cfg = ExternalAlmacenesSetting::current();
        $tempCfg = clone $cfg;

        $fields = ['host', 'port', 'username', 'database_name', 'table_name', 'schema_name', 'timeout'];
        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) !== null) {
                $tempCfg->{$field} = $request->input($field);
            }
        }
        if ($request->filled('password')) {
            $tempCfg->attributes['password'] = encrypt($request->input('password'));
        }

        $result = ExternalAlmacenesService::testConnection($tempCfg);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function rawAlmacenes(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.almacenes-externos.test'), 403);

        $idUnidad = $request->integer('id_unidad') ?: null;
        $result   = ExternalAlmacenesService::fetchRaw(null, $idUnidad);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function previewSyncAlmacenes(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.almacenes-externos.sync'), 403);

        $data = $request->validate([
            'sales_floor_id'   => 'required|integer|exists:pisos_venta,id',
            'override_id_unidad' => 'nullable|integer|min:1',
        ]);

        $result = ExternalAlmacenesService::buildSyncPreview(
            (int) $data['sales_floor_id'],
            isset($data['override_id_unidad']) ? (int) $data['override_id_unidad'] : null
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function applySyncAlmacenes(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('configuracion.almacenes-externos.sync'), 403);

        $data = $request->validate([
            'sales_floor_id'     => 'required|integer|exists:pisos_venta,id',
            'items'              => 'required|array|min:1',
            'items.*.action'     => 'required|string|in:create,update',
            'items.*.almacen_nombre'   => 'required_if:items.*.action,create|string|max:255',
            'items.*.almacen_id'       => 'nullable|integer',
            'items.*.id_almacen_local' => 'nullable|integer',
            'items.*.almacen_tipo'     => 'nullable|string|max:50',
            'items.*.almacen_abierto'  => 'boolean',
            'items.*.almacen_mlc'      => 'boolean',
            'items.*.local_area_id'    => 'nullable|integer',
        ]);

        $result = ExternalAlmacenesService::applySync(
            (int) $data['sales_floor_id'],
            $data['items']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
