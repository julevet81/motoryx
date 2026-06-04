<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Sale;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator
    ) {}

    public function create(Tenant $tenant, array $data): Sale
    {
        return DB::transaction(function () use ($tenant, $data) {
            /** @var Car $car */
            $car = Car::forTenant($tenant->id)->lockForUpdate()->findOrFail($data['car_id']);

            if ($car->status !== 'available') {
                throw new \DomainException("Car [{$car->id}] is not available for sale.");
            }

            $data['tenant_id']  = $tenant->id;
            $data['sale_number'] = $this->numberGenerator->nextSaleNumber($tenant->id);

            $sale = Sale::create($data);

            // Mark car as sold
            $car->update(['status' => 'sold']);

            return $sale->load(['customer', 'car', 'salesperson']);
        });
    }

    public function addPayment(Sale $sale, array $data): Sale
    {
        $data['sale_id'] = $sale->id;
        $sale->payments()->create($data);

        // Auto-complete if fully paid
        if ($sale->fresh()->remaining_amount <= 0) {
            $sale->update(['status' => 'completed']);
        }

        return $sale->load('payments');
    }

    public function cancel(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $sale->update(['status' => 'cancelled']);

            // Return car to available
            Car::where('id', $sale->car_id)->update(['status' => 'available']);

            return $sale;
        });
    }
}
