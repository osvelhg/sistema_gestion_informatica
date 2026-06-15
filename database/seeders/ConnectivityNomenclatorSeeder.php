<?php

namespace Database\Seeders;

use App\Models\EstablishmentStatus;
use App\Models\EstablishmentType;
use App\Models\NetworkType;
use Illuminate\Database\Seeder;

class ConnectivityNomenclatorSeeder extends Seeder
{
    public function run(): void
    {
        // Tipos de Red Comercial
        $networkTypes = [
            ['name' => 'RED CUP',    'color' => 'blue'],
            ['name' => 'RED MLC',    'color' => 'green'],
            ['name' => 'Mixta',      'color' => 'cyan'],
            ['name' => 'Hotelera',   'color' => 'yellow'],
            ['name' => 'Virtual',    'color' => 'violet'],
            ['name' => 'Mi Pieza',   'color' => 'slate'],
        ];
        foreach ($networkTypes as $data) {
            NetworkType::firstOrCreate(['name' => $data['name']], $data + ['active' => true]);
        }

        // Tipos de Establecimiento
        $establishmentTypes = ['CUP', 'MLC', 'MIXTO'];
        foreach ($establishmentTypes as $name) {
            EstablishmentType::firstOrCreate(['name' => $name], ['active' => true]);
        }

        // Estados del Establecimiento
        $statuses = ['Abierto', 'Cerrado'];
        foreach ($statuses as $name) {
            EstablishmentStatus::firstOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
