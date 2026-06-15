<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'HP',
            'Dell',
            'Lenovo',
            'Acer',
            'Asus',
            'Samsung',
            'LG',
            'Epson',
            'Canon',
            'Xerox',
            'Intel',
            'AMD',
            'Kingston',
            'Seagate',
            'Western Digital',
        ];

        foreach ($brands as $name) {
            Brand::updateOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
