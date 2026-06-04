<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SubscriptionPlanSeeder::class,
            BrandSeeder::class,
            VehicleCategorySeeder::class,
            CarFeatureSeeder::class,
            TenantSeeder::class,
        ]);
        User::find(1)->assignRole('admin');
        User::find(2)->assignRole('manager');
        User::find(3)->assignRole('agent');
    }
}
