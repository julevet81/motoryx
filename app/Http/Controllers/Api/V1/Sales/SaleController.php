<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    use ApiResponser, LogsActivity;

    public function __construct(private readonly SaleService $saleService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'         => 'string|in:pending,completed,cancelled,refunded',
            'salesperson_id' => 'integer|exists:users,id',
            'date_from'      => 'date',
            'date_to'        => 'date|after_or_equal:date_from',
            'per_page'       => 'integer|min:1|max:50',
        ]);

        $tenant  = app('tenant');
        $user    = $request->user();
        $perPage = min((int) $request->input('per_page', 15), 50);

        $query = Sale::forTenant($tenant->id)
            ->with(['customer', 'car.brand', 'car.model', 'salesperson'])
            ->when($request->status,         fn($q, $v) => $q->where('status', $v))
            ->when($request->date_from,      fn($q, $v) => $q->where('sale_date', '>=', $v))
            ->when($request->date_to,        fn($q, $v) => $q->where('sale_date', '<=', $v))
            ->when($request->salesperson_id, fn($q, $v) => $q->where('salesperson_id', $v))
            ->when(! $user->isManager(),     fn($q)     => $q->where('salesperson_id', $user->id))
            ->latest('sale_date');

        return $this->paginatedResponse($query->paginate($perPage), fn($s) => $this->saleResource($s));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'customer_id'    => 'required|integer',
            'car_id'         => 'required|integer',
            'salesperson_id' => 'required|integer',
            'sale_date'      => 'required|date',
            'vehicle_price'  => 'required|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0',
        ]);

        $this->validateBelongsToTenant('users', $data['salesperson_id'], $tenant->id);
        $this->validateBelongsToTenant('customers', $data['customer_id'], $tenant->id);

        $data['total'] = max(0, $data['vehicle_price'] - ($data['discount'] ?? 0));

        $sale = $this->saleService->create($tenant, $data);
        $this->logActivity('sale.created', $sale, ['sale_number' => $sale->sale_number]);

        return $this->createdResponse($this->saleResource($sale), 'Sale created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $user = request()->user();

        $sale = Sale::forTenant(app('tenant')->id)
            ->with(['customer', 'car.brand', 'car.model', 'salesperson', 'payments'])
            ->when(! $user->isManager(), fn($q) => $q->where('salesperson_id', $user->id))
            ->findOrFail($id);

        return $this->successResponse($this->saleResource($sale, detailed: true));
    }

    public function cancel(int $id): JsonResponse
    {
        $sale = Sale::forTenant(app('tenant')->id)->findOrFail($id);

        if ($sale->status === 'completed') {
            return $this->errorResponse('Cannot cancel a completed sale.', 422);
        }
        if ($sale->status === 'cancelled') {
            return $this->errorResponse('Sale is already cancelled.', 422);
        }

        $sale = $this->saleService->cancel($sale);
        $this->logActivity('sale.cancelled', $sale);

        return $this->successResponse($this->saleResource($sale), 'Sale cancelled.');
    }

    public function addPayment(Request $request, int $id): JsonResponse
    {
        $sale = Sale::forTenant(app('tenant')->id)->findOrFail($id);

        if ($sale->status === 'cancelled') {
            return $this->errorResponse('Cannot add payment to a cancelled sale.', 422);
        }

        $data = $request->validate([
            'payment_date'   => 'required|date',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,check,card',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:500',
        ]);

        if ($data['amount'] > $sale->remaining_amount) {
            return $this->errorResponse('Payment amount exceeds remaining balance.', 422);
        }

        $sale = $this->saleService->addPayment($sale, $data);
        $this->logActivity('sale.payment_added', $sale, ['amount' => $data['amount']]);

        return $this->createdResponse($this->saleResource($sale->load('payments')), 'Payment added.');
    }

    private function validateBelongsToTenant(string $table, int $id, int $tenantId): void
    {
        $exists = DB::table($table)->where('id', $id)->where('tenant_id', $tenantId)->exists();
        if (! $exists) {
            abort(422, "The selected {$table} ID is invalid.");
        }
    }

    private function saleResource(Sale $sale, bool $detailed = false): array
    {
        $data = [
            'id'            => $sale->id,
            'sale_number'   => $sale->sale_number,
            'sale_date'     => $sale->sale_date->toDateString(),
            'status'        => $sale->status,
            'vehicle_price' => $sale->vehicle_price,
            'discount'      => $sale->discount,
            'total'         => $sale->total,
            'paid_amount'   => $sale->paid_amount,
            'remaining'     => $sale->remaining_amount,
            'customer'      => $sale->relationLoaded('customer')
                ? $sale->customer->only('id', 'first_name', 'last_name', 'phone')
                : null,
            'car'           => $sale->relationLoaded('car') ? [
                'id'    => $sale->car->id,
                'brand' => $sale->car->brand?->name,
                'model' => $sale->car->model?->name,
                'year'  => $sale->car->year,
                'vin'   => $sale->car->vin,
            ] : null,
            'salesperson'   => $sale->relationLoaded('salesperson')
                ? $sale->salesperson->only('id', 'first_name', 'last_name')
                : null,
            'created_at'    => $sale->created_at->toIso8601String(),
        ];

        if ($detailed && $sale->relationLoaded('payments')) {
            $data['payments'] = $sale->payments->map(fn($p) => [
                'id'             => $p->id,
                'payment_date'   => $p->payment_date->toDateString(),
                'amount'         => $p->amount,
                'payment_method' => $p->payment_method,
                'reference'      => $p->reference,
            ])->values();
        }

        return $data;
    }
}
