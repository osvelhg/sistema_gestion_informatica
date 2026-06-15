<?php

namespace Database\Seeders;

use App\Models\ComponentType;
use Illuminate\Database\Seeder;

class ComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'motherboard',  'name' => 'Tarjeta Madre',         'category' => 'caracteristica'],
            ['slug' => 'cpu',          'name' => 'Microprocesador',        'category' => 'caracteristica'],
            ['slug' => 'ram',          'name' => 'Memoria RAM',            'category' => 'caracteristica'],
            ['slug' => 'power_supply', 'name' => 'Fuente de Alimentación', 'category' => 'caracteristica'],
            ['slug' => 'reader',       'name' => 'Lector',                 'category' => 'caracteristica'],
            ['slug' => 'hdd',          'name' => 'Disco Duro',             'category' => 'caracteristica'],
            ['slug' => 'monitor',      'name' => 'Monitor',                'category' => 'periferico'],
            ['slug' => 'keyboard',     'name' => 'Teclado',               'category' => 'periferico'],
            ['slug' => 'mouse',        'name' => 'Mouse',                  'category' => 'periferico'],
            ['slug' => 'speakers',     'name' => 'Bocinas',                'category' => 'periferico'],
            ['slug' => 'printer',      'name' => 'Impresora',              'category' => 'periferico'],
            ['slug' => 'scanner',      'name' => 'Scanner',                'category' => 'periferico'],
            ['slug' => 'backup',       'name' => 'Backup',                 'category' => 'periferico'],
            ['slug' => 'ups',          'name' => 'UPS',                    'category' => 'dispositivo'],
            ['slug' => 'other',        'name' => 'Otro Dispositivo',       'category' => 'dispositivo'],
        ];

        foreach ($types as $type) {
            ComponentType::firstOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
