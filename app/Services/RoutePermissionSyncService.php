<?php

namespace App\Services;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class RoutePermissionSyncService
{
    private const EXCLUDED_NAMES = [
        'profile.edit',
        'profile.update',
        'profile.password',
    ];

    /**
     * Sincroniza permisos con todas las rutas web nombradas.
     * Para rutas sin nombre, crea un permiso técnico derivado.
     */
    public static function sync(): void
    {
        $routePermissions = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(function (Route $route) {
                $middleware = $route->gatherMiddleware();

                // Solo rutas autenticadas/validadas (ignora públicas).
                $isSecured = in_array('auth', $middleware, true) && in_array('verified', $middleware, true);
                if (!$isSecured) {
                    return false;
                }

                // Excluir rutas de gestión de perfil personal.
                $name = $route->getName();
                if (!empty($name) && in_array($name, self::EXCLUDED_NAMES, true)) {
                    return false;
                }

                return true;
            })
            ->map(function (Route $route) {
                $name = $route->getName();
                if (!empty($name)) {
                    return $name;
                }

                $method = Str::lower($route->methods()[0] ?? 'get');
                $uri = trim($route->uri(), '/');
                $slug = str_replace(['/', '{', '}', '-'], ['.', '', '', '_'], $uri);
                $slug = preg_replace('/\.+/', '.', $slug ?? '');
                $slug = trim((string) $slug, '.');

                return $slug !== '' ? "ruta.{$method}.{$slug}" : "ruta.{$method}.raiz";
            })
            ->unique()
            ->values();

        $existing = Permission::query()->pluck('name');
        $toCreate = $routePermissions->diff($existing);
        $toDelete = $existing->diff($routePermissions);

        foreach ($toCreate as $permissionName) {
            Permission::create(['name' => $permissionName]);
        }

        if ($toDelete->isNotEmpty()) {
            Permission::query()->whereIn('name', $toDelete->values())->delete();
        }
    }
}
