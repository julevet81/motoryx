<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use ApiResponser, LogsActivity;

    public function index(): JsonResponse
    {
        $branches = Branch::forTenant(app('tenant')->id)
            ->withCount(['users', 'cars'])
            ->get();

        return $this->successResponse($branches->map(fn($b) => $this->branchResource($b)));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        if (! $tenant->withinLimit('branches')) {
            return $this->errorResponse('You have reached the branches limit for your plan.', 403);
        }

        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'phone'     => 'nullable|string|max:30',
            'email'     => 'nullable|email|max:255',
            'address'   => 'nullable|string|max:500',
            'city'      => 'nullable|string|max:100',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'nullable|boolean',
        ]);

        $data['tenant_id'] = $tenant->id;
        $branch = Branch::create($data);

        $this->logActivity('branch.created', $branch);

        return $this->createdResponse($this->branchResource($branch), 'Branch created.');
    }

    public function show(int $id): JsonResponse
    {
        $branch = Branch::forTenant(app('tenant')->id)
            ->withCount(['users', 'cars'])
            ->findOrFail($id);

        return $this->successResponse($this->branchResource($branch));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::forTenant(app('tenant')->id)->findOrFail($id);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:150',
            'phone'     => 'sometimes|nullable|string|max:30',
            'email'     => 'sometimes|nullable|email|max:255',
            'address'   => 'sometimes|nullable|string|max:500',
            'city'      => 'sometimes|nullable|string|max:100',
            'latitude'  => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($data);
        $this->logActivity('branch.updated', $branch);

        return $this->successResponse($this->branchResource($branch), 'Branch updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $branch = Branch::forTenant(app('tenant')->id)
            ->withCount(['users', 'cars'])
            ->findOrFail($id);

        if ($branch->users_count > 0) {
            return $this->errorResponse('Cannot delete branch with active users. Reassign them first.', 422);
        }
        if ($branch->cars_count > 0) {
            return $this->errorResponse('Cannot delete branch with cars assigned to it.', 422);
        }

        $branch->delete();
        $this->logActivity('branch.deleted', $branch);

        return $this->noContentResponse();
    }

    private function branchResource(Branch $branch): array
    {
        return [
            'id'          => $branch->id,
            'name'        => $branch->name,
            'phone'       => $branch->phone,
            'email'       => $branch->email,
            'address'     => $branch->address,
            'city'        => $branch->city,
            'latitude'    => $branch->latitude,
            'longitude'   => $branch->longitude,
            'is_active'   => $branch->is_active,
            'users_count' => $branch->users_count ?? null,
            'cars_count'  => $branch->cars_count ?? null,
            'created_at'  => $branch->created_at->toIso8601String(),
        ];
    }
}
