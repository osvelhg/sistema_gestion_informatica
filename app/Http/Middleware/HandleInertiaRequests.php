<?php

namespace App\Http\Middleware;

use App\Models\SystemModule;
use App\Support\Branding;
use App\Support\UserEntityAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id'    => $request->user()->id,
                    'name'  => $request->user()->name,
                    'email' => $request->user()->email,
                    'roles' => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    'ad_provincia_sigla' => $request->user()->ad_provincia_sigla,
                    'entity_access_mode' => $request->user()->entity_access_mode,
                    'entity_access_bypass' => UserEntityAccess::bypasses($request->user()),
                ] : null,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'csrf_token' => fn () => csrf_token(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'modules' => fn () => SystemModule::query()->pluck('enabled', 'slug'),
            'branding' => fn () => array_merge(
                Branding::resolvedLayout(),
                ['logo_url' => Branding::logoPublicUrl()],
            ),
        ];
    }
}
