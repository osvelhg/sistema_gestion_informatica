<?php

namespace App\Providers;

use App\Models\EquipmentFile;
use App\Policies\EquipmentFilePolicy;
use App\Services\LdapService;
use App\Support\Branding;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(EquipmentFile::class, EquipmentFilePolicy::class);
        Gate::before(function ($user) {
            return $user?->hasRole('Administrador') ? true : null;
        });

        // Carga la conexión LDAP desde la BD en runtime (si está configurada y habilitada).
        // Envuelto en try/catch para no romper la app si la BD aún no está disponible
        // (ej: durante migraciones o comandos Artisan iniciales).
        try {
            LdapService::boot();
        } catch (\Throwable) {
            // LDAP no disponible — la app sigue funcionando sin AD
        }

        View::composer('pdf.*', function ($view) {
            $view->with([
                'branding' => Branding::resolvedLayout(),
                'brandingLogoDataUrl' => Branding::logoDataUrlForPdf(),
            ]);
        });
    }
}
