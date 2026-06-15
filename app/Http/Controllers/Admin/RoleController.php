<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoutePermissionSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        // Sincroniza permisos contra rutas antes de administrar roles.
        RoutePermissionSyncService::sync();

        $roles = Role::withCount('permissions')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permisos,name',
        ]);

        $role = Role::create(['name' => $data['name']]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => "required|string|max:255|unique:roles,name,{$role->id}",
            'permissions' => 'array',
            'permissions.*' => 'exists:permisos,name',
        ]);

        $role->update(['name' => $data['name']]);
        if ($role->name === 'Administrador') {
            $role->syncPermissions([]);
        } else {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'Administrador') {
            return back()->with('error', 'No se puede eliminar el rol Administrador.');
        }

        $role->delete();

        return back()->with('success', 'Rol eliminado correctamente.');
    }
}
