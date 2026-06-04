<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Reservation;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    use ApiResponser, LogsActivity;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'string|in:pending,confirmed,cancelled,completed',
            'per_page' => 'integer|min:1|max:50',
        ]);
        $tenant  = app('tenant');
        $perPage = min((int) $request->input('per_page', 15), 50);
        $query = Reservation::forTenant($tenant->id)
            ->with(['customer', 'car.brand', 'car.model'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest('created_at');
        return $this->paginatedResponse($query->paginate($perPage), fn($r) => $this->reservationResource($r));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'customer_id'      => 'required|integer',
            'car_id'           => 'required|integer',
            'reservation_date' => 'required|date|after:now',
            'deposit_amount'   => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string|max:1000',
        ]);
        return DB::transaction(function () use ($tenant, $data) {
            $car = Car::forTenant($tenant->id)->lockForUpdate()->findOrFail($data['car_id']);
            if ($car->status !== 'available') {
                return $this->errorResponse('This car is not available for reservation.', 422);
            }
            $customerExists = DB::table('customers')
                ->where('id', $data['customer_id'])
                ->where('tenant_id', $tenant->id)
                ->exists();
            if (! $customerExists) {
                return $this->errorResponse('Invalid customer.', 422);
            }
            $reservation = Reservation::create([
                'tenant_id'        => $tenant->id,
                'customer_id'      => $data['customer_id'],
                'car_id'           => $car->id,
                'reservation_date' => $data['reservation_date'],
                'deposit_amount'   => $data['deposit_amount'] ?? 0,
                'status'           => 'pending',
                'notes'            => $data['notes'] ?? null,
            ]);
            $car->update(['status' => 'reserved']);
            $this->logActivity('reservation.created', $reservation);
            return $this->createdResponse(
                $this->reservationResource($reservation->load(['customer', 'car.brand', 'car.model'])),
                'Reservation created.'
            );
        });
    }

    public function show(int $id): JsonResponse
    {
        $reservation = Reservation::forTenant(app('tenant')->id)
            ->with(['customer', 'car.brand', 'car.model'])
            ->findOrFail($id);
        return $this->successResponse($this->reservationResource($reservation));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::forTenant(app('tenant')->id)->findOrFail($id);
        $data = $request->validate([
            'status' => 'required|string|in:pending,confirmed,cancelled,completed',
        ]);
        return DB::transaction(function () use ($reservation, $data) {
            $oldStatus = $reservation->status;
            $reservation->update(['status' => $data['status']]);
            if ($data['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
                Car::where('id', $reservation->car_id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available']);
            }
            $this->logActivity('reservation.status_updated', $reservation, ['from' => $oldStatus, 'to' => $data['status']]);
            return $this->successResponse(
                $this->reservationResource($reservation->load(['customer', 'car.brand', 'car.model'])),
                'Status updated.'
            );
        });
    }

    private function reservationResource(Reservation $r): array
    {
        return [
            'id'               => $r->id,
            'status'           => $r->status,
            'reservation_date' => $r->reservation_date->toIso8601String(),
            'deposit_amount'   => $r->deposit_amount,
            'notes'            => $r->notes,
            'customer'         => $r->relationLoaded('customer')
                ? $r->customer->only('id', 'first_name', 'last_name', 'phone') : null,
            'car'              => $r->relationLoaded('car') ? [
                'id'    => $r->car->id,
                'brand' => $r->car->brand?->name,
                'model' => $r->car->model?->name,
                'year'  => $r->car->year,
            ] : null,
            'created_at'       => $r->created_at->toIso8601String(),
        ];
    }
}
