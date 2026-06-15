<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\User;
use App\Support\UserEntityAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['roles', 'entities'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
            ->when($request->role, fn($q, $r) => $q->role($r))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'role'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Form', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'entities' => Entity::active()->orderBy('code')->orderBy('name')->get(['id', 'name', 'code']),
            'entityAccessModes' => [
                ['value' => UserEntityAccess::MODE_PROVINCE_DIRECTORY, 'label' => 'Directorio provincial (AD): todas las entidades de mi provincia'],
                ['value' => UserEntityAccess::MODE_RESTRICTED_ENTITIES, 'label' => 'Entidades concretas (mayor confidencialidad)'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:usuarios,email',
            'password' => ['required', Password::defaults()],
            'active' => 'boolean',
            'role' => 'required|string|exists:roles,name',
            'entity_access_mode' => 'required|string|in:'.UserEntityAccess::MODE_PROVINCE_DIRECTORY.','.UserEntityAccess::MODE_RESTRICTED_ENTITIES,
            'entity_ids' => 'array',
            'entity_ids.*' => 'exists:entidades,id',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'active' => $data['active'] ?? true,
            'entity_access_mode' => $data['entity_access_mode'],
        ]);

        $user->assignRole($data['role']);

        if (($data['entity_access_mode'] ?? '') === UserEntityAccess::MODE_RESTRICTED_ENTITIES) {
            $user->entities()->sync($data['entity_ids'] ?? []);
        } else {
            $user->entities()->sync([]);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $usuario->load(['roles', 'entities']);

        return Inertia::render('Admin/Users/Form', [
            'user' => $usuario,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'entities' => Entity::active()->orderBy('code')->orderBy('name')->get(['id', 'name', 'code']),
            'entityAccessModes' => [
                ['value' => UserEntityAccess::MODE_PROVINCE_DIRECTORY, 'label' => 'Directorio provincial (AD): todas las entidades de mi provincia'],
                ['value' => UserEntityAccess::MODE_RESTRICTED_ENTITIES, 'label' => 'Entidades concretas (mayor confidencialidad)'],
            ],
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:usuarios,email,{$usuario->id}",
            'password' => ['nullable', Password::defaults()],
            'active' => 'boolean',
            'role' => 'required|string|exists:roles,name',
            'entity_access_mode' => 'required|string|in:'.UserEntityAccess::MODE_PROVINCE_DIRECTORY.','.UserEntityAccess::MODE_RESTRICTED_ENTITIES,
            'entity_ids' => 'array',
            'entity_ids.*' => 'exists:entidades,id',
        ]);

        $usuario->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'active' => $data['active'] ?? true,
            'entity_access_mode' => $data['entity_access_mode'],
        ]);

        if (!empty($data['password'])) {
            $usuario->update(['password' => Hash::make($data['password'])]);
        }

        $usuario->syncRoles([$data['role']]);
        if (($data['entity_access_mode'] ?? '') === UserEntityAccess::MODE_RESTRICTED_ENTITIES) {
            $usuario->entities()->sync($data['entity_ids'] ?? []);
        } else {
            $usuario->entities()->sync([]);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        $usuario->delete();

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
