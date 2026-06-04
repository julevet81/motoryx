<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'           => 'Starter',
                'monthly_price'  => 29.00,
                'users_limit'    => 3,
                'cars_limit'     => 50,
                'branches_limit' => 1,
                'is_active'      => true,
            ],
            [
                'name'           => 'Professional',
                'monthly_price'  => 79.00,
                'users_limit'    => 10,
                'cars_limit'     => 200,
                'branches_limit' => 3,
                'is_active'      => true,
            ],
            [
                'name'           => 'Business',
                'monthly_price'  => 149.00,
                'users_limit'    => 30,
                'cars_limit'     => 1000,
                'branches_limit' => 10,
                'is_active'      => true,
            ],
            [
                'name'           => 'Enterprise',
                'monthly_price'  => 299.00,
                'users_limit'    => 999,
                'cars_limit'     => 99999,
                'branches_limit' => 999,
                'is_active'      => true,
            ],
        ];

        DB::table('subscription_plans')->insertOrIgnore($plans);
    }
}
