<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminUserSeeder::class,
            StatusSeeder::class,
            ComponentTypeSeeder::class,
            BrandSeeder::class,
            ComponentModelSeeder::class,
            WorksheetAspectSeeder::class,
            SystemModuleSeeder::class,
            CashRegisterModelSeeder::class,
            ConnectivityNomenclatorSeeder::class,
        ]);
    }
}
