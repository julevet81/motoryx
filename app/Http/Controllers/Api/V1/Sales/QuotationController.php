<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\NumberGeneratorService;
use App\Traits\ApiResponser;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    use ApiResponser, LogsActivity;

    public function __construct(private readonly NumberGeneratorService $numberGenerator) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'string|in:draft,sent,accepted,rejected,expired',
            'per_page' => 'integer|min:1|max:50',
        ]);

        $tenant  = app('tenant');
        $user    = $request->user();
        $perPage = min((int) $request->input('per_page', 15), 50);

        $query = Quotation::forTenant($tenant->id)
            ->with(['customer', 'salesperson'])
            ->when($request->status,     fn($q, $v) => $q->where('status', $v))
            ->when(! $user->isManager(), fn($q)     => $q->where('salesperson_id', $user->id))
            ->latest();

        return $this->paginatedResponse($query->paginate($perPage), fn($q) => $this->quotationResource($q));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'customer_id'       => 'required|integer',
            'salesperson_id'    => 'required|integer',
            'valid_until'       => 'nullable|date|after:today',
            'status'            => 'nullable|string|in:draft,sent',
            'items'             => 'required|array|min:1|max:20',
            'items.*.car_id'    => 'required|integer',
            'items.*.discount'  => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($tenant, $data) {
            $customerExists = DB::table('customers')
                ->where('id', $data['customer_id'])
                ->where('tenant_id', $tenant->id)
                ->exists();

            if (! $customerExists) {
                return $this->errorResponse('Invalid customer.', 422);
            }

            $carIds = array_column($data['items'], 'car_id');
            $cars   = Car::forTenant($tenant->id)
                ->whereIn('id', $carIds)
                ->get()
                ->keyBy('id');

            if ($cars->count() !== count(array_unique($carIds))) {
                return $this->errorResponse('One or more car IDs are invalid.', 422);
            }

            $subtotal = 0;
            $items    = [];
            foreach ($data['items'] as $item) {
                $car      = $cars[$item['car_id']];
                $price    = (float) ($car->discounted_price ?? $car->selling_price ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $total    = max(0, $price - $discount);
                $subtotal += $total;

                $items[] = [
                    'car_id'     => $car->id,
                    'unit_price' => $price,
                    'discount'   => $discount,
                    'total'      => $total,
                ];
            }

            $quotation = Quotation::create([
                'tenant_id'        => $tenant->id,
                'quotation_number' => $this->numberGenerator->nextQuotationNumber($tenant->id),
                'customer_id'      => $data['customer_id'],
                'salesperson_id'   => $data['salesperson_id'],
                'subtotal'         => $subtotal,
                'discount'         => 0,
                'total'            => $subtotal,
                'status'           => $data['status'] ?? 'draft',
                'valid_until'      => $data['valid_until'] ?? null,
            ]);

            foreach ($items as $item) {
                QuotationItem::create(array_merge($item, ['quotation_id' => $quotation->id]));
            }

            $this->logActivity('quotation.created', $quotation);

            return $this->createdResponse(
                $this->quotationResource($quotation->load(['customer', 'salesperson', 'items.car'])),
                'Quotation created.'
            );
        });
    }

    public function show(int $id): JsonResponse
    {
        $user      = request()->user();
        $quotation = Quotation::forTenant(app('tenant')->id)
            ->with(['customer', 'salesperson', 'items.car.brand', 'items.car.model'])
            ->when(! $user->isManager(), fn($q) => $q->where('salesperson_id', $user->id))
            ->findOrFail($id);

        return $this->successResponse($this->quotationResource($quotation, detailed: true));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::forTenant(app('tenant')->id)->findOrFail($id);

        $data = $request->validate([
            'status' => 'required|string|in:draft,sent,accepted,rejected,expired',
        ]);

        $quotation->update($data);
        $this->logActivity('quotation.status_updated', $quotation, ['status' => $data['status']]);

        return $this->successResponse($this->quotationResource($quotation), 'Status updated.');
    }

    private function quotationResource(Quotation $quotation, bool $detailed = false): array
    {
        $data = [
            'id'               => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'status'           => $quotation->status,
            'subtotal'         => $quotation->subtotal,
            'discount'         => $quotation->discount,
            'total'            => $quotation->total,
            'valid_until'      => $quotation->valid_until?->toDateString(),
            'is_expired'       => $quotation->isExpired(),
            'customer'         => $quotation->relationLoaded('customer')
                ? $quotation->customer->only('id', 'first_name', 'last_name', 'phone')
                : null,
            'salesperson'      => $quotation->relationLoaded('salesperson')
                ? $quotation->salesperson->only('id', 'first_name', 'last_name')
                : null,
            'created_at'       => $quotation->created_at->toIso8601String(),
        ];

        if ($detailed && $quotation->relationLoaded('items')) {
            $data['items'] = $quotation->items->map(fn($item) => [
                'id'         => $item->id,
                'unit_price' => $item->unit_price,
                'discount'   => $item->discount,
                'total'      => $item->total,
                'car'        => $item->car ? [
                    'id'    => $item->car->id,
                    'brand' => $item->car->brand?->name,
                    'model' => $item->car->model?->name,
                    'year'  => $item->car->year,
                ] : null,
            ])->values();
        }

        return $data;
    }
}
