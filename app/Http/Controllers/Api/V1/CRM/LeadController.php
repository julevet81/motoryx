<?php

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    use ApiResponser, LogsActivity;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'      => 'string|in:new,contacted,qualified,negotiating,won,lost',
            'assigned_to' => 'integer|exists:users,id',
            'source'      => 'string|max:50',
            'per_page'    => 'integer|min:1|max:50',
        ]);

        $tenant  = app('tenant');
        $user    = $request->user();
        $perPage = min((int) $request->input('per_page', 15), 50);

        $query = Lead::forTenant($tenant->id)
            ->with(['customer', 'assignedTo'])
            ->when($request->status,      fn($q, $v) => $q->where('status', $v))
            ->when($request->source,      fn($q, $v) => $q->where('source', $v))
            ->when($request->assigned_to, fn($q, $v) => $q->where('assigned_to', $v))
            ->when(! $user->isManager(),  fn($q)     => $q->assignedTo($user->id))
            ->latest();

        return $this->paginatedResponse($query->paginate($perPage), fn($l) => $this->leadResource($l));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'customer_id' => 'required|integer',
            'assigned_to' => 'nullable|integer',
            'source'      => 'nullable|string|in:website,walk_in,phone,social_media,referral,other',
            'status'      => 'nullable|string|in:new,contacted,qualified,negotiating,won,lost',
            'notes'       => 'nullable|string|max:2000',
        ]);

        $this->validateBelongsToTenant('customers', $data['customer_id'], $tenant->id);

        if (! empty($data['assigned_to'])) {
            $this->validateBelongsToTenant('users', $data['assigned_to'], $tenant->id);
        }

        $data['tenant_id'] = $tenant->id;
        $lead = Lead::create($data);

        LeadActivity::create([
            'lead_id'  => $lead->id,
            'user_id'  => $request->user()->id,
            'activity' => 'created',
            'notes'    => 'Lead created.',
        ]);

        $this->logActivity('lead.created', $lead);

        return $this->createdResponse($this->leadResource($lead->load(['customer', 'assignedTo'])));
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::forTenant(app('tenant')->id)
            ->with(['customer', 'assignedTo', 'activities.user'])
            ->findOrFail($id);

        $this->authorizeLeadAccess($lead);

        return $this->successResponse($this->leadResource($lead, detailed: true));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = app('tenant');
        $lead   = Lead::forTenant($tenant->id)->findOrFail($id);
        $this->authorizeLeadAccess($lead);

        $data = $request->validate([
            'assigned_to' => 'nullable|integer',
            'source'      => 'nullable|string|in:website,walk_in,phone,social_media,referral,other',
            'status'      => 'nullable|string|in:new,contacted,qualified,negotiating,won,lost',
            'notes'       => 'nullable|string|max:2000',
        ]);

        if (! empty($data['assigned_to'])) {
            $this->validateBelongsToTenant('users', $data['assigned_to'], $tenant->id);
        }

        $oldStatus = $lead->status;
        $lead->update($data);

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            LeadActivity::create([
                'lead_id'  => $lead->id,
                'user_id'  => $request->user()->id,
                'activity' => 'status_change',
                'notes'    => "Status changed from {$oldStatus} to {$data['status']}.",
            ]);
        }

        $this->logActivity('lead.updated', $lead);

        return $this->successResponse($this->leadResource($lead->load(['customer', 'assignedTo'])), 'Lead updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $lead = Lead::forTenant(app('tenant')->id)->findOrFail($id);
        $lead->delete();
        $this->logActivity('lead.deleted', $lead);

        return $this->noContentResponse();
    }

    public function addActivity(Request $request, int $id): JsonResponse
    {
        $lead = Lead::forTenant(app('tenant')->id)->findOrFail($id);
        $this->authorizeLeadAccess($lead);

        $data = $request->validate([
            'activity' => 'required|string|in:call,email,meeting,note,status_change,other',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $activity = LeadActivity::create([
            'lead_id'  => $lead->id,
            'user_id'  => $request->user()->id,
            'activity' => $data['activity'],
            'notes'    => $data['notes'] ?? null,
        ]);

        return $this->createdResponse([
            'id'         => $activity->id,
            'activity'   => $activity->activity,
            'notes'      => $activity->notes,
            'user'       => $request->user()->only('id', 'first_name', 'last_name'),
            'created_at' => $activity->created_at->toIso8601String(),
        ], 'Activity logged.');
    }

    private function authorizeLeadAccess(Lead $lead): void
    {
        $user = request()->user();
        if (! $user->isManager() && (int) $lead->assigned_to !== $user->id) {
            abort(403, 'Access denied.');
        }
    }

    private function validateBelongsToTenant(string $table, int $id, int $tenantId): void
    {
        $exists = DB::table($table)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $exists) {
            abort(422, "The selected {$table} ID is invalid.");
        }
    }

    private function leadResource(Lead $lead, bool $detailed = false): array
    {
        $data = [
            'id'          => $lead->id,
            'customer'    => $lead->relationLoaded('customer')
                ? $lead->customer->only('id', 'first_name', 'last_name', 'phone', 'email')
                : null,
            'assigned_to' => $lead->relationLoaded('assignedTo')
                ? $lead->assignedTo?->only('id', 'first_name', 'last_name')
                : null,
            'source'      => $lead->source,
            'status'      => $lead->status,
            'notes'       => $lead->notes,
            'created_at'  => $lead->created_at->toIso8601String(),
        ];

        if ($detailed && $lead->relationLoaded('activities')) {
            $data['activities'] = $lead->activities->map(fn($a) => [
                'id'         => $a->id,
                'activity'   => $a->activity,
                'notes'      => $a->notes,
                'user'       => $a->user?->only('id', 'first_name', 'last_name'),
                'created_at' => $a->created_at->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}
