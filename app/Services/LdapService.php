<?php

namespace App\Services;

use App\Models\LdapSetting;
use Illuminate\Support\Facades\Log;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

class LdapService
{
    /** Diagnóstico de la última búsqueda (para APP_DEBUG / consola del navegador). */
    private static ?array $lastSearchDiagnostics = null;

    /** Motivo del último fallo al registrar la conexión desde BD (p. ej. excepción). */
    private static ?string $lastRegisterConnectionMessage = null;

    public static function getLastSearchDiagnostics(): ?array
    {
        return self::$lastSearchDiagnostics;
    }

    /**
     * Sustituye la conexión LdapRecord "default" (la de config/ldap.php, suele ser 127.0.0.1)
     * por la definida en la BD.
     */
    private static function registerDefaultConnection(LdapSetting $cfg): void
    {
        $connection = static::makeConnection($cfg);

        if (Container::getInstance()->hasConnection('default')) {
            Container::getInstance()->removeConnection('default');
        }

        Container::addConnection($connection, 'default');
    }

    /**
     * Registra la conexión desde BD si LDAP está habilitado y hay host.
     * Sin esto, las búsquedas usarían ldap://127.0.0.1 de config/ldap.php.
     */
    public static function registerConnectionFromDatabaseIfReady(): bool
    {
        self::$lastRegisterConnectionMessage = null;

        $cfg = LdapSetting::current();

        if (! $cfg->enabled || ! $cfg->host) {
            self::$lastRegisterConnectionMessage = 'LDAP deshabilitado o sin host en la fila de ajustes_ldap.';

            return false;
        }

        try {
            static::registerDefaultConnection($cfg);

            return true;
        } catch (\Throwable $e) {
            self::$lastRegisterConnectionMessage = $e->getMessage();
            Log::error('[LDAP] registerConnectionFromDatabaseIfReady falló', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Lee LdapSetting de la BD y registra la conexión en el Container de LdapRecord.
     * Se llama desde AppServiceProvider::boot() envuelto en try/catch.
     */
    public static function boot(): void
    {
        $cfg = LdapSetting::current();

        if (! $cfg->enabled || ! $cfg->host) {
            return;
        }

        static::registerDefaultConnection($cfg);
    }

    /**
     * Construye un objeto Connection a partir de una instancia de LdapSetting.
     */
    public static function makeConnection(LdapSetting $cfg): Connection
    {
        return new Connection([
            'hosts'            => [$cfg->host],
            'port'             => $cfg->port ?? 389,
            'base_dn'          => $cfg->base_dn ?? '',
            'username'         => $cfg->bind_username ?? '',
            'password'         => $cfg->bind_password ?? '',
            'use_ssl'          => $cfg->use_ssl,
            'use_tls'          => $cfg->use_tls,
            'timeout'          => $cfg->timeout ?? 5,
            'follow_referrals' => false,
            'version'          => 3,
        ]);
    }

    /**
     * Prueba la conexión y devuelve un array con el resultado.
     * No modifica el Container — sólo verifica los parámetros provistos.
     */
    public static function testConnection(LdapSetting $cfg): array
    {
        if (! $cfg->host) {
            return ['success' => false, 'message' => 'El servidor (host) no está configurado.'];
        }

        try {
            $connection = static::makeConnection($cfg);
            $connection->connect();

            // Intenta un query básico para confirmar que el bind funcionó
            $connection->query()->setDn($cfg->base_dn ?? '')->limit(1)->get();

            return [
                'success' => true,
                'message' => 'Conexión exitosa. El servidor AD respondió correctamente.',
            ];
        } catch (\LdapRecord\Auth\BindException $e) {
            $detail = $e->getDetailedError();
            return [
                'success' => false,
                'message' => 'Error de autenticación en el servidor AD.',
                'details' => $detail
                    ? "Código {$detail->getErrorCode()}: {$detail->getErrorMessage()}"
                    : $e->getMessage(),
            ];
        } catch (\LdapRecord\LdapRecordException $e) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar al servidor AD.',
                'details' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error inesperado al intentar la conexión.',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Busca usuarios en el AD por sAMAccountName, displayName o mail.
     * Requiere que la conexión 'default' esté registrada en el Container.
     *
     * @return array<int, array{samaccountname: string, displayname: string, mail: string, ou: string}>
     */
    public static function searchUsers(string $query): array
    {
        self::$lastSearchDiagnostics = null;

        $query = trim($query);
        if (strlen($query) < 2) {
            self::$lastSearchDiagnostics = [
                'skipped' => true,
                'reason'  => 'Consulta vacía o demasiado corta (mínimo 2 caracteres).',
                'query'   => $query,
            ];
            Log::info('[LDAP] searchUsers omitida', self::$lastSearchDiagnostics);

            return [];
        }

        $cfg = LdapSetting::current();
        $baseDn = $cfg->user_search_base ?: $cfg->base_dn;
        $escapedForFilter = self::escapeLdapFilterValue($query);
        $filterDescription = '(|(samaccountname=*'.$escapedForFilter.'*)(displayname=*'.$escapedForFilter.'*)(mail=*'.$escapedForFilter.'*)(userprincipalname=*'.$escapedForFilter.'*))';

        if (! static::registerConnectionFromDatabaseIfReady()) {
            self::$lastSearchDiagnostics = [
                'step'    => 'no_ldap_connection',
                'reason'  => self::$lastRegisterConnectionMessage ?? 'No se pudo registrar la conexión LDAP.',
                'enabled' => (bool) $cfg->enabled,
                'host'    => $cfg->host ?: null,
                'hint'    => 'La prueba del formulario no usa el mismo código que la búsqueda: hace falta registrar la conexión en LdapRecord sin error.',
            ];
            Log::warning('[LDAP] searchUsers: no hay conexión registrada desde BD', self::$lastSearchDiagnostics);

            return [];
        }

        try {
            $diag = [
                'step'              => 'search',
                'query_raw'         => $query,
                'ldap_host_used'    => $cfg->host,
                'base_dn_effective' => $baseDn ?: '(vacío)',
                'user_search_base'  => $cfg->user_search_base ?: null,
                'base_dn_config'    => $cfg->base_dn ?: null,
                'filter_approx'     => $filterDescription,
                'note'              => 'Filtro OR en sAMAccountName, displayName, mail y userPrincipalName (subcadena). Comprueba que user_search_base / base DN incluya el contenedor del usuario.',
            ];
            Log::info('[LDAP] searchUsers inicio', $diag);

            $results = LdapUser::on('default')
                ->setDn($baseDn ?? '')
                ->orFilter(function ($q) use ($query) {
                    $q->whereContains('samaccountname', $query)
                        ->orWhereContains('displayname', $query)
                        ->orWhereContains('mail', $query)
                        ->orWhereContains('userprincipalname', $query);
                })
                ->select(['samaccountname', 'displayname', 'mail', 'distinguishedname', 'userprincipalname'])
                ->limit(50)
                ->get();

            $mapped = $results->map(function ($user) {
                $dn = $user->getDn() ?? '';
                // Extraer la primera OU del DN para mostrar dónde está el usuario
                preg_match('/OU=([^,]+)/i', $dn, $m);

                return [
                    'samaccountname' => $user->getFirstAttribute('samaccountname') ?? '',
                    'displayname'    => $user->getFirstAttribute('displayname') ?? '',
                    'mail'           => $user->getFirstAttribute('mail') ?? '',
                    'ou'             => $m[1] ?? '',
                ];
            })->toArray();

            $diag['result_count'] = count($mapped);
            $diag['sample_dns'] = $results->take(5)->map(fn ($u) => $u->getDn())->filter()->values()->all();
            self::$lastSearchDiagnostics = $diag;

            Log::info('[LDAP] searchUsers fin', [
                'result_count' => $diag['result_count'],
                'sample_dns'   => $diag['sample_dns'],
            ]);

            return $mapped;
        } catch (\Throwable $e) {
            $errorDiag = [
                'step'              => 'error',
                'query_raw'         => $query,
                'base_dn_effective' => $baseDn ?: '(vacío)',
                'user_search_base'  => $cfg->user_search_base ?: null,
                'base_dn_config'    => $cfg->base_dn ?: null,
                'filter_approx'     => $filterDescription,
                'exception'         => $e::class,
                'message'           => $e->getMessage(),
            ];
            self::$lastSearchDiagnostics = $errorDiag;

            Log::error('[LDAP] searchUsers falló', array_merge($errorDiag, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
            Log::debug('[LDAP] searchUsers trace', ['trace' => $e->getTraceAsString()]);

            return [];
        }
    }

    /**
     * Escapa un valor para uso literal en filtros LDAP (RFC 4515).
     */
    private static function escapeLdapFilterValue(string $value): string
    {
        if (\function_exists('ldap_escape')) {
            $flags = \defined('LDAP_ESCAPE_FILTER') ? \LDAP_ESCAPE_FILTER : 2;

            return ldap_escape($value, '', $flags);
        }

        return str_replace(
            ['\\', '*', '(', ')', "\x00"],
            ['\\5c', '\\2a', '\\28', '\\29', '\\00'],
            $value
        );
    }

    /**
     * Intenta autenticar un usuario AD con sus credenciales.
     * Primero busca al usuario por sAMAccountName o UPN, luego hace re-bind con su DN.
     *
     * @return array{dn: string, email: string, name: string}|null
     */
    public static function authenticateUser(string $login, string $password): ?array
    {
        try {
            $cfg = LdapSetting::current();

            if (! static::registerConnectionFromDatabaseIfReady()) {
                return null;
            }

            $connection = Container::getConnection('default');
            $baseDn       = $cfg->user_search_base ?: $cfg->base_dn;

            // Buscar usuario por sAMAccountName o por UPN/mail si contiene @
            $builder = LdapUser::on('default')->setDn($baseDn ?? '');

            if (str_contains($login, '@')) {
                $ldapUser = $builder->orFilter(fn ($q) =>
                    $q->where('userprincipalname', '=', $login)
                      ->orWhere('mail', '=', $login)
                )->first();
            } else {
                $ldapUser = $builder->where('samaccountname', '=', $login)->first();
            }

            if (! $ldapUser) return null;

            $dn = $ldapUser->getDn();
            if (! $dn) return null;

            // Re-bind como el usuario para verificar su contraseña
            if (! $connection->auth()->attempt($dn, $password)) {
                return null;
            }

            return [
                'dn'                   => $dn,
                'email'                => $ldapUser->getFirstAttribute('mail')
                        ?? $ldapUser->getFirstAttribute('userprincipalname')
                        ?? ($login . '@' . static::extractDomain($cfg->base_dn ?? '')),
                'name'                 => $ldapUser->getFirstAttribute('displayname')
                        ?? $ldapUser->getFirstAttribute('cn')
                        ?? $login,
                'ad_provincia_sigla'   => static::extractProvinceSiglaFromDn($dn),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Primera componente DC= del DN (orden en el string), p.ej.
     * OU=Site ART,DC=art,DC=trd,... → ART (normalizado en mayúsculas).
     */
    public static function extractProvinceSiglaFromDn(string $dn): ?string
    {
        if ($dn === '') {
            return null;
        }
        if (preg_match_all('/DC=([^,]+)/i', $dn, $m) && ! empty($m[1][0])) {
            return strtoupper(trim($m[1][0]));
        }

        return null;
    }

    /**
     * Extrae el dominio desde un base_dn, ej: dc=empresa,dc=cu → empresa.cu
     */
    private static function extractDomain(string $baseDn): string
    {
        preg_match_all('/DC=([^,]+)/i', $baseDn, $matches);
        return implode('.', $matches[1] ?? []) ?: 'local';
    }
}
