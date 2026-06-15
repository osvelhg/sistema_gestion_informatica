<?php

namespace App\Http\Middleware;

use App\Models\SystemModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $enabled = SystemModule::query()
            ->where('slug', $moduleSlug)
            ->value('enabled');

        if ($enabled === false) {
            abort(403, "El modulo {$moduleSlug} no esta habilitado para produccion.");
        }

        return $next($request);
    }
}
