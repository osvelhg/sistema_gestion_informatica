<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Bien',    'color' => 'green',  'order' => 1],
            ['name' => 'Regular', 'color' => 'yellow', 'order' => 2],
            ['name' => 'Mal',     'color' => 'red',    'order' => 3],
        ];

        foreach ($statuses as $s) {
            Status::firstOrCreate(['name' => $s['name']], $s);
        }
    }
}
