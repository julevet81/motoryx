<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo tenant
        $tenantId = DB::table('tenants')->insertGetId([
            'name'       => 'AutoDeal Showroom',
            'slug'       => 'autodeal',
            'email'      => 'admin@autodeal.com',
            'phone'      => '+966501234567',
            'city'       => 'Riyadh',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign to Business plan
        $planId = DB::table('subscription_plans')->where('name', 'Business')->value('id');
        DB::table('tenant_subscriptions')->insert([
            'tenant_id'  => $tenantId,
            'plan_id'    => $planId,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create main branch
        $branchId = DB::table('branches')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => 'Main Branch - Riyadh',
            'phone'      => '+966112345678',
            'email'      => 'riyadh@autodeal.com',
            'city'       => 'Riyadh',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create super admin
        DB::table('users')->insert([
            [
                'tenant_id'         => $tenantId,
                'branch_id'         => $branchId,
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'email'             => 'admin@autodeal.com',
                'phone'             => '+966501234567',
                'password'          => Hash::make('12345678'),
                'status'            => 'active',
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'tenant_id'         => $tenantId,
                'branch_id'         => $branchId,
                'first_name'        => 'Sales',
                'last_name'         => 'Manager',
                'email'             => 'manager@autodeal.com',
                'phone'             => '+966501234568',
                'password'          => Hash::make('12345678'),
                'status'            => 'active',
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'tenant_id'         => $tenantId,
                'branch_id'         => $branchId,
                'first_name'        => 'Sales',
                'last_name'         => 'Agent',
                'email'             => 'agent@autodeal.com',
                'phone'             => '+966501234569',
                'password'          => Hash::make('12345678'),
                'status'            => 'active',
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);
    }
}
