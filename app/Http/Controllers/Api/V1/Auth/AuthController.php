<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponser, LogsActivity;

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('tenant');

        $user = User::query()
            ->forTenant($tenant->id)
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials.', 401);
        }

        if (! $user->isActive()) {
            return $this->errorResponse('Your account is suspended.', 403);
        }

        // Revoke old tokens for same device if device_name provided
        $deviceName = $request->device_name ?? 'api';
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName, ['*'], now()->addDays(30));

        $this->logActivity('auth.login', $user);

        return $this->successResponse([
            'user' => $this->userResource($user),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ], 'Logged in successfully.');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();
        $this->logActivity('auth.logout');

        return $this->successResponse(null, 'Logged out successfully.');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->userResource($request->user()->load('branch'))
        );
    }

    /**
     * PUT /api/v1/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
            'avatar' => 'sometimes|nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store("avatars/{$request->user()->tenant_id}", 'public');
        }

        $request->user()->update($validated);

        return $this->successResponse($this->userResource($request->user()->fresh()), 'Profile updated.');
    }

    /**
     * PUT /api/v1/auth/password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            return $this->errorResponse('Current password is incorrect.', 422);
        }

        $request->user()->update(['password' => $request->password]);

        // Revoke all other tokens on password change (security)
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        $this->logActivity('auth.password_changed');

        return $this->successResponse(null, 'Password changed successfully.');
    }

    private function userResource(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'avatar' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'branch' => $user->relationLoaded('branch') ? $user->branch?->only('id', 'name') : null,
            'tenant_id' => $user->tenant_id,
        ];
    }
}
