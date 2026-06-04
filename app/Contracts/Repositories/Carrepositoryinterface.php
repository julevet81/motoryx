<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CarRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters, int $perPage): LengthAwarePaginator;

    public function findByVin(string $vin): ?\App\Models\Car;

    public function getAvailableForTenant(int $tenantId): \Illuminate\Database\Eloquent\Collection;

    public function syncFeatures(int $carId, array $featureIds): void;

    public function reorderImages(int $carId, array $orderedImageIds): void;
}
