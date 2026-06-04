<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Sedan',
            'SUV',
            'Pickup Truck',
            'Hatchback',
            'Coupe',
            'Convertible',
            'Van',
            'Minivan',
            'Crossover',
            'Wagon',
            'Sports Car',
            'Luxury',
            'Electric',
            'Hybrid',
        ];

        $rows = array_map(fn($name) => ['name' => $name], $categories);
        DB::table('vehicle_categories')->insertOrIgnore($rows);
    }
}
