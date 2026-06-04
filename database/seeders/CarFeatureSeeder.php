<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CarFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            'Air Conditioning',
            'Cruise Control',
            'Sunroof',
            'Navigation System',
            'Bluetooth',
            'Backup Camera',
            'Parking Sensors',
            'Heated Seats',
            'Leather Seats',
            'Apple CarPlay',
            'Android Auto',
            'Keyless Entry',
            'Push Button Start',
            'Lane Departure Warning',
            'Blind Spot Monitor',
            'Adaptive Cruise Control',
            'Automatic Emergency Braking',
            '360° Camera',
            'Wireless Charging',
            'Premium Sound System',
            'Third Row Seating',
            'Roof Rack',
            'Tow Package',
            'All-Wheel Drive',
            '4WD',
            'Sport Mode',
            'Eco Mode',
            'HUD Display',
            'Memory Seats',
            'Power Liftgate',
        ];

        $rows = array_map(fn($name) => ['name' => $name], $features);
        DB::table('car_features')->insertOrIgnore($rows);
    }
}
