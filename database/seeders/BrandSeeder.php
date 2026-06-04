<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Toyota',
            'Honda',
            'Nissan',
            'Hyundai',
            'Kia',
            'Mercedes-Benz',
            'BMW',
            'Audi',
            'Volkswagen',
            'Ford',
            'Chevrolet',
            'Jeep',
            'Mitsubishi',
            'Mazda',
            'Subaru',
            'Lexus',
            'Infiniti',
            'Porsche',
            'Volvo',
            'Land Rover',
            'Renault',
            'Peugeot',
            'Citroën',
            'Fiat',
            'Alfa Romeo',
            'Suzuki',
            'Isuzu',
            'GMC',
            'Cadillac',
            'Lincoln',
        ];

        $now = now();
        $rows = array_map(fn($name) => [
            'name'       => $name,
            'logo'       => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $brands);

        foreach (array_chunk($rows, 10) as $chunk) {
            DB::table('brands')->insertOrIgnore($chunk);
        }

        // Seed car models for Toyota and BMW as examples
        $toyotaId = DB::table('brands')->where('name', 'Toyota')->value('id');
        $bmwId    = DB::table('brands')->where('name', 'BMW')->value('id');

        $models = [
            // Toyota
            ['brand_id' => $toyotaId, 'name' => 'Camry'],
            ['brand_id' => $toyotaId, 'name' => 'Corolla'],
            ['brand_id' => $toyotaId, 'name' => 'Land Cruiser'],
            ['brand_id' => $toyotaId, 'name' => 'RAV4'],
            ['brand_id' => $toyotaId, 'name' => 'Hilux'],
            ['brand_id' => $toyotaId, 'name' => 'Prado'],
            // BMW
            ['brand_id' => $bmwId, 'name' => '3 Series'],
            ['brand_id' => $bmwId, 'name' => '5 Series'],
            ['brand_id' => $bmwId, 'name' => '7 Series'],
            ['brand_id' => $bmwId, 'name' => 'X5'],
            ['brand_id' => $bmwId, 'name' => 'X6'],
        ];

        DB::table('car_models')->insertOrIgnore($models);
    }
}
