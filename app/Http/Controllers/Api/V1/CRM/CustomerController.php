<?php

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponser, LogsActivity;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search'   => 'string|max:100',
            'per_page' => 'integer|min:1|max:50',
        ]);

        $tenant    = app('tenant');
        $perPage   = min((int) $request->input('per_page', 15), 50);

        $paginator = Customer::forTenant($tenant->id)
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate($perPage);

        return $this->paginatedResponse($paginator, fn($c) => $this->customerResource($c));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email|max:255',
            'address'    => 'nullable|string|max:500',
            'city'       => 'nullable|string|max:100',
            'notes'      => 'nullable|string|max:2000',
        ]);

        $data['tenant_id'] = app('tenant')->id;
        $customer          = Customer::create($data);

        $this->logActivity('customer.created', $customer);

        return $this->createdResponse($this->customerResource($customer));
    }

    public function show(int $id): JsonResponse
    {
        $customer = Customer::forTenant(app('tenant')->id)->findOrFail($id);

        return $this->successResponse($this->customerResource($customer));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::forTenant(app('tenant')->id)->findOrFail($id);

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'phone'      => 'sometimes|nullable|string|max:30',
            'email'      => 'sometimes|nullable|email|max:255',
            'address'    => 'sometimes|nullable|string|max:500',
            'city'       => 'sometimes|nullable|string|max:100',
            'notes'      => 'sometimes|nullable|string|max:2000',
        ]);

        $customer->update($data);
        $this->logActivity('customer.updated', $customer);

        return $this->successResponse($this->customerResource($customer), 'Customer updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::forTenant(app('tenant')->id)->findOrFail($id);
        $customer->delete();
        $this->logActivity('customer.deleted', $customer);

        return $this->noContentResponse();
    }

    private function customerResource(Customer $customer): array
    {
        return [
            'id'         => $customer->id,
            'full_name'  => $customer->full_name,
            'first_name' => $customer->first_name,
            'last_name'  => $customer->last_name,
            'phone'      => $customer->phone,
            'email'      => $customer->email,
            'city'       => $customer->city,
            'notes'      => $customer->notes,
            'created_at' => $customer->created_at->toIso8601String(),
        ];
    }
}
