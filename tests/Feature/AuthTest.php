<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = SubscriptionPlan::create([
            'name'           => 'Test Plan',
            'monthly_price'  => 29,
            'users_limit'    => 10,
            'cars_limit'     => 100,
            'branches_limit' => 3,
        ]);

        $this->tenant = Tenant::create([
            'name'      => 'Test Tenant',
            'slug'      => 'test',
            'email'     => 'tenant@test.com',
            'is_active' => true,
        ]);

        TenantSubscription::create([
            'tenant_id'  => $this->tenant->id,
            'plan_id'    => $plan->id,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'status'     => 'active',
        ]);

        $this->user = User::create([
            'tenant_id'         => $this->tenant->id,
            'first_name'        => 'Test',
            'last_name'         => 'User',
            'email'             => 'user@test.com',
            'password'          => 'password',
            'role'              => 'admin',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'password',
        ], ['X-Tenant' => 'test']);

        $response->assertOk()
                 ->assertJsonStructure([
                     'success', 'data' => ['user', 'token', 'expires_at']
                 ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'wrong-password',
        ], ['X-Tenant' => 'test'])
        ->assertStatus(401);
    }

    public function test_login_fails_without_tenant_header(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'password',
        ])
        ->assertStatus(400);
    }

    public function test_me_endpoint_returns_user(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
            'X-Tenant'      => 'test',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'user@test.com');
    }

    public function test_cross_tenant_access_is_blocked(): void
    {
        // Create another tenant
        $otherTenant = Tenant::create([
            'name'      => 'Other Tenant',
            'slug'      => 'other',
            'email'     => 'other@test.com',
            'is_active' => true,
        ]);

        // Use token from tenant A but pass tenant B header
        $token = $this->user->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
            'X-Tenant'      => 'other',
        ])
        ->assertStatus(403);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $this->user->update(['status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'password',
        ], ['X-Tenant' => 'test'])
        ->assertStatus(403);
    }
}
