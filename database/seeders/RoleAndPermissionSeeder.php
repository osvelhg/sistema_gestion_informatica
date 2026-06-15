<?php

namespace Database\Seeders;

use App\Services\RoutePermissionSyncService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        RoutePermissionSyncService::sync();

        $admin = Role::firstOrCreate(['name' => 'Administrador']);
        $admin->syncPermissions([]);

    }
}
