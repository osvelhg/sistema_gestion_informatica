<?php

namespace Database\Seeders;

use App\Models\SystemModule;
use Illuminate\Database\Seeder;

class SystemModuleSeeder extends Seeder
{
    public function run(): void
    {
        SystemModule::updateOrCreate(
            ['slug' => 'expedientes'],
            ['name' => 'Expedientes', 'enabled' => true, 'description' => 'Gestion de expedientes y anexos historicos.']
        );

        SystemModule::updateOrCreate(
            ['slug' => 'conectividad'],
            ['name' => 'Conectividad', 'enabled' => true, 'description' => 'Conciliacion de conectividad POS con Fincimex.']
        );

        SystemModule::updateOrCreate(
            ['slug' => 'codigos-qr'],
            ['name' => 'Código QR', 'enabled' => true, 'description' => 'Gestion de fuentes QR, trabajadores asociados e importacion desde JSON.']
        );

        SystemModule::updateOrCreate(
            ['slug' => 'facturacion-etecsa'],
            ['name' => 'Facturación ETECSA', 'enabled' => true, 'description' => 'Importacion y analisis de facturas PDF de ETECSA (telefonia y conectividad).']
        );
    }
}
