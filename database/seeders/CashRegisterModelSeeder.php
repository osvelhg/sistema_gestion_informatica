<?php

namespace Database\Seeders;

use App\Models\CashRegisterModel;
use Illuminate\Database\Seeder;

class CashRegisterModelSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 1, 'name' => 'Casio'],
            ['code' => 2, 'name' => 'Optima'],
            ['code' => 3, 'name' => 'Apos04'],
            ['code' => 4, 'name' => 'Apos05'],
            ['code' => 5, 'name' => 'PC'],
        ];

        foreach ($rows as $row) {
            CashRegisterModel::updateOrCreate(['code' => $row['code']], ['name' => $row['name'], 'active' => true]);
        }
    }
}
