<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ComponentModel;
use App\Models\ComponentType;
use Illuminate\Database\Seeder;

class ComponentModelSeeder extends Seeder
{
    public function run(): void
    {
        $modelsByType = [
            'cpu' => [
                ['brand' => 'Intel', 'name' => 'Core i5'],
                ['brand' => 'Intel', 'name' => 'Core i7'],
                ['brand' => 'AMD', 'name' => 'Ryzen 5'],
            ],
            'ram' => [
                ['brand' => 'Kingston', 'name' => 'DDR4 8GB'],
                ['brand' => 'Kingston', 'name' => 'DDR4 16GB'],
            ],
            'hdd' => [
                ['brand' => 'Seagate', 'name' => '1TB'],
                ['brand' => 'Western Digital', 'name' => '1TB'],
            ],
            'monitor' => [
                ['brand' => 'Samsung', 'name' => '22 pulgadas'],
                ['brand' => 'LG', 'name' => '24 pulgadas'],
            ],
            'printer' => [
                ['brand' => 'Epson', 'name' => 'L3110'],
                ['brand' => 'Canon', 'name' => 'G3110'],
            ],
        ];

        foreach ($modelsByType as $typeSlug => $rows) {
            $type = ComponentType::query()->where('slug', $typeSlug)->first();
            if (!$type) {
                continue;
            }

            foreach ($rows as $row) {
                $brand = Brand::query()->where('name', $row['brand'])->first();
                if (!$brand) {
                    continue;
                }

                ComponentModel::updateOrCreate(
                    [
                        'component_type_id' => $type->id,
                        'brand_id' => $brand->id,
                        'name' => $row['name'],
                    ],
                    ['active' => true]
                );
            }
        }
    }
}
