<?php

namespace App\Providers;

use App\Models\LdapSetting;
use App\Models\User;
use App\Services\LdapService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use LdapRecord\Container;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::loginView(function () {
            return Inertia::render('Auth/Login');
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            return Limit::perMinute(5)->by($email . $request->ip());
        });

        /*
        |----------------------------------------------------------------------
        | Autenticación personalizada: AD (LDAP) con fallback a BD local
        |----------------------------------------------------------------------
        | El campo del formulario se llama "email" pero puede contener:
        |   - sAMAccountName:  jperez
        |   - UPN / email:     jperez@empresa.cu
        |
        | Flujo:
        |   1. Si LDAP habilitado → intentar autenticar en AD
        |      - Éxito: find-or-create usuario local (email AD, sin rol)
        |   2. Fallback: autenticación estándar con contraseña en BD
        */
        Fortify::authenticateUsing(function (Request $request) {
            $login    = trim((string) $request->email);
            $password = (string) $request->password;

            if ($login === '' || $password === '') return null;

            // ── Intento 1: Active Directory ─────────────────────────────────
            $ldapCfg = LdapSetting::current();

            if ($ldapCfg->enabled && $ldapCfg->host && Container::getInstance()->hasConnection('default')) {
                $adUser = LdapService::authenticateUser($login, $password);

                if ($adUser !== null) {
                    // Crear o recuperar el usuario local enlazado al AD
                    $user = User::firstOrCreate(
                        ['email' => $adUser['email']],
                        [
                            'name'     => $adUser['name'],
                            'password' => Hash::make(Str::random(40)),
                            'active'   => true,
                        ]
                    );

                    if (! empty($adUser['ad_provincia_sigla'])) {
                        $user->ad_provincia_sigla = $adUser['ad_provincia_sigla'];
                        $user->save();
                    }

                    return $user;
                }
                // Si AD falló, continúa al fallback local (no lanza excepción)
            }

            // ── Intento 2: Fallback — contraseña local en BD ─────────────────
            $user = User::where('email', $login)
                ->where('active', true)
                ->first();

            if ($user && Hash::check($password, $user->password)) {
                return $user;
            }

            return null;
        });
    }
}
