<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponser, LogsActivity;

    public function index(Request $request): JsonResponse
    {
        $tenant  = app('tenant');
        $perPage = min((int) $request->input('per_page', 15), 50);
        $users = User::forTenant($tenant->id)
            ->with('branch:id,name')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->role,   fn($q, $v) => $q->where('role', $v))
            ->when($request->search, fn($q, $s) => $q->where(fn($q) => $q
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name',  'like', "%{$s}%")
                ->orWhere('email',      'like', "%{$s}%")
            ))
            ->latest()
            ->paginate($perPage);
        return $this->paginatedResponse($users, fn($u) => $this->userResource($u));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        if (! $tenant->withinLimit('users')) {
            return $this->errorResponse('You have reached the users limit for your plan.', 403);
        }
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|string|max:30',
            'password'   => 'required|string|min:8',
            'role'       => ['required', Rule::in(['manager', 'agent'])],
            'branch_id'  => 'nullable|integer',
            'status'     => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        if (! empty($data['branch_id'])) {
            $branchExists = DB::table('branches')->where('id', $data['branch_id'])->where('tenant_id', $tenant->id)->exists();
            if (! $branchExists) {
                return $this->errorResponse('Invalid branch.', 422);
            }
        }
        $data['tenant_id']         = $tenant->id;
        $data['email_verified_at'] = now();
        $data['status']            = $data['status'] ?? 'active';
        $user = User::create($data);
        $this->logActivity('user.created', $user);
        return $this->createdResponse($this->userResource($user->load('branch')), 'User created.');
    }

    public function show(int $id): JsonResponse
    {
        $user = User::forTenant(app('tenant')->id)->with('branch:id,name')->findOrFail($id);
        return $this->successResponse($this->userResource($user));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = app('tenant');
        $user   = User::forTenant($tenant->id)->findOrFail($id);
        if ($user->id === $request->user()->id) {
            return $this->errorResponse('Use the profile endpoint to update your own account.', 422);
        }
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'phone'      => 'sometimes|nullable|string|max:30',
            'role'       => ['sometimes', Rule::in(['manager', 'agent'])],
            'branch_id'  => 'sometimes|nullable|integer',
            'status'     => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);
        if (! empty($data['branch_id'])) {
            $branchExists = DB::table('branches')->where('id', $data['branch_id'])->where('tenant_id', $tenant->id)->exists();
            if (! $branchExists) {
                return $this->errorResponse('Invalid branch.', 422);
            }
        }
        $user->update($data);
        if (($data['status'] ?? null) === 'suspended') {
            $user->tokens()->delete();
        }
        $this->logActivity('user.updated', $user);
        return $this->successResponse($this->userResource($user->fresh('branch')), 'User updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::forTenant(app('tenant')->id)->findOrFail($id);
        if ($user->id === request()->user()->id) {
            return $this->errorResponse('You cannot delete your own account.', 422);
        }
        $user->tokens()->delete();
        $user->delete();
        $this->logActivity('user.deleted', $user);
        return $this->noContentResponse();
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::forTenant(app('tenant')->id)->findOrFail($id);
        $data = $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();
        $this->logActivity('user.password_reset', $user);
        return $this->successResponse(null, 'Password reset. User must login again.');
    }

    private function userResource(User $user): array
    {
        return [
            'id'         => $user->id,
            'full_name'  => $user->full_name,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'status'     => $user->status,
            'avatar'     => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'branch'     => $user->relationLoaded('branch') ? $user->branch?->only('id', 'name') : null,
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
